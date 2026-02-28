<?php

namespace splendidweb\googlereviews\services;

use Craft;
use craft\base\Component;
use craft\helpers\StringHelper;
use splendidweb\googlereviews\models\SyncResult;
use splendidweb\googlereviews\elements\GoogleReview;
use splendidweb\googlereviews\Plugin;
use DateTime;
use RuntimeException;
use Throwable;

class SyncService extends Component
{
    private const GBP_API_BASE_URL = 'https://mybusiness.googleapis.com/v4';

    public function sync(): SyncResult
    {
        $result = new SyncResult();
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enabled) {
            Craft::warning('Google Reviews sync skipped: plugin disabled.', __METHOD__);
            $result->skipped++;
            return $result;
        }

        try {
            if (!$settings->isMockMode()) {
                $result->archived += $this->removeMockReviews();
            }

            $rawReviews = $this->fetchReviews();
            $result->fetched = count($rawReviews);

            foreach ($rawReviews as $rawReview) {
                $normalized = $this->normalizeReview($rawReview);

                if (!$this->passesFilters($normalized)) {
                    $result->skipped++;
                    continue;
                }

                $this->upsertReviewEntry($normalized);
                $result->upserted++;
            }
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $result->errors[] = $message;
            Craft::error('Google Reviews sync failed: ' . $message, __METHOD__);
        }

        Craft::info(
            sprintf(
                'Google Reviews sync complete. fetched=%d upserted=%d skipped=%d archived=%d errors=%d',
                $result->fetched,
                $result->upserted,
                $result->skipped,
                $result->archived,
                count($result->errors)
            ),
            __METHOD__
        );

        return $result;
    }

    private function removeMockReviews(): int
    {
        $mockReviews = GoogleReview::find()
            ->googleReviewId('mock-*')
            ->status(null)
            ->all();

        $removed = 0;
        foreach ($mockReviews as $mockReview) {
            if (!$mockReview instanceof GoogleReview) {
                continue;
            }

            if (Craft::$app->getElements()->deleteElement($mockReview)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchReviews(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $max = max(1, (int)$settings->maxReviews);

        if ($settings->isMockMode()) {
            return array_slice($this->mockReviews(), 0, min(3, $max));
        }

        if ($settings->isPlacesMode()) {
            return $this->fetchPlacesReviews($max);
        }

        $accountId = trim($settings->getParsedGoogleAccountId());
        $locationId = trim($settings->getParsedGoogleLocationId());
        $clientId = trim($settings->getParsedOAuthClientId());
        $clientSecret = trim($settings->getParsedOAuthClientSecret());
        $refreshToken = trim($settings->getParsedOAuthRefreshToken());

        if ($accountId === '' || $locationId === '') {
            throw new RuntimeException('Google account/location IDs are required when Business Profile mode is selected.');
        }

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException('OAuth client ID, client secret, and refresh token are required when Business Profile mode is selected.');
        }

        $accessToken = $this->fetchAccessToken($clientId, $clientSecret, $refreshToken);

        $accountResource = $this->normalizeResourceName($accountId, 'accounts');
        $locationResource = $this->normalizeResourceName($locationId, 'locations');
        $endpoint = sprintf('%s/%s/%s/reviews', self::GBP_API_BASE_URL, $accountResource, $locationResource);

        $reviews = [];
        $pageToken = null;
        $client = Craft::createGuzzleClient();

        do {
            $query = [
                'pageSize' => min(50, max(1, $max)),
            ];
            if ($pageToken) {
                $query['pageToken'] = $pageToken;
            }

            $response = $client->request('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'http_errors' => false,
                'timeout' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string)$response->getBody(), true);

            if ($statusCode >= 400) {
                $error = is_array($body) ? json_encode($body) : (string)$response->getBody();
                throw new RuntimeException('Google reviews API request failed (' . $statusCode . '): ' . $error);
            }

            $batch = $body['reviews'] ?? [];
            if (!is_array($batch)) {
                $batch = [];
            }

            $reviews = array_merge($reviews, $batch);
            $pageToken = $body['nextPageToken'] ?? null;
        } while ($pageToken && count($reviews) < $max);

        return array_slice($reviews, 0, $max);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPlacesReviews(int $max): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $apiKey = trim($settings->getParsedPlacesApiKey());
        $placeId = trim($settings->getParsedPlacesPlaceId());

        if ($apiKey === '' || $placeId === '') {
            throw new RuntimeException('Places API key and Place ID are required when Places mode is enabled.');
        }

        $placeResource = str_starts_with($placeId, 'places/') ? $placeId : 'places/' . $placeId;

        $client = Craft::createGuzzleClient();
        $response = $client->request('GET', 'https://places.googleapis.com/v1/' . $placeResource, [
            'headers' => [
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'id,reviews,googleMapsUri',
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
            'timeout' => 20,
        ]);

        $statusCode = $response->getStatusCode();
        $body = json_decode((string)$response->getBody(), true);

        if ($statusCode >= 400 || !is_array($body)) {
            $error = is_array($body) ? json_encode($body) : (string)$response->getBody();
            throw new RuntimeException('Places API (New) request failed (' . $statusCode . '): ' . $error);
        }

        $reviews = $body['reviews'] ?? [];
        if (!is_array($reviews)) {
            $reviews = [];
        }

        $placeUrl = (string)($body['googleMapsUri'] ?? '');
        $normalizedRaw = [];
        foreach ($reviews as $index => $review) {
            if (!is_array($review)) {
                continue;
            }

            $authorAttribution = is_array($review['authorAttribution'] ?? null) ? $review['authorAttribution'] : [];
            $reviewText = is_array($review['text'] ?? null)
                ? (string)($review['text']['text'] ?? '')
                : (string)($review['text'] ?? '');
            $publishTime = (string)($review['publishTime'] ?? '');
            $authorUri = (string)($authorAttribution['uri'] ?? '');
            $authorPhotoUri = (string)($authorAttribution['photoUri'] ?? '');

            $rawId = $authorUri . '|' . $publishTime . '|' . $index;
            $normalizedRaw[] = [
                'reviewId' => 'places-' . md5($rawId),
                'reviewer' => [
                    'displayName' => (string)($authorAttribution['displayName'] ?? ''),
                    'profilePhotoUrl' => $authorPhotoUri,
                ],
                'starRating' => $review['rating'] ?? 0,
                'comment' => $reviewText,
                'createTime' => $publishTime !== '' ? $publishTime : null,
                'reviewLink' => (string)($authorUri !== '' ? $authorUri : $placeUrl),
                'reviewReply' => null,
            ];
        }

        return array_slice($normalizedRaw, 0, $max);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mockReviews(): array
    {
        return [
            [
                'reviewId' => 'mock-1001',
                'reviewer' => [
                    'displayName' => 'Alex Morgan',
                    'profilePhotoUrl' => '',
                ],
                'starRating' => 5,
                'comment' => 'Great service and a super smooth experience.',
                'createTime' => '2026-02-12T10:30:00+00:00',
                'reviewLink' => 'https://www.google.com/maps',
            ],
            [
                'reviewId' => 'mock-1002',
                'reviewer' => [
                    'displayName' => 'Jordan Lee',
                    'profilePhotoUrl' => '',
                ],
                'starRating' => 4,
                'comment' => 'Friendly team and quick turnaround.',
                'createTime' => '2026-02-14T09:00:00+00:00',
                'reviewLink' => 'https://www.google.com/maps',
            ],
            [
                'reviewId' => 'mock-1003',
                'reviewer' => [
                    'displayName' => 'Sam Carter',
                    'profilePhotoUrl' => '',
                ],
                'starRating' => 5,
                'comment' => 'Highly recommend. Will use again.',
                'createTime' => '2026-02-18T14:15:00+00:00',
                'reviewLink' => 'https://www.google.com/maps',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    private function normalizeReview(array $review): array
    {
        $reviewId = (string)($review['reviewId'] ?? '');
        if ($reviewId === '' && !empty($review['name']) && is_string($review['name'])) {
            $reviewId = (string)preg_replace('/^.*\/reviews\//', '', $review['name']);
        }

        return [
            'googleReviewId' => $reviewId,
            'authorName' => (string)($review['reviewer']['displayName'] ?? ''),
            'authorPhotoUrl' => (string)($review['reviewer']['profilePhotoUrl'] ?? ''),
            'rating' => $this->normalizeStarRating($review['starRating'] ?? 0),
            'reviewText' => (string)($review['comment'] ?? ''),
            'reviewDate' => $review['createTime'] ?? null,
            'replyText' => (string)($review['reviewReply']['comment'] ?? ''),
            'replyUpdatedAt' => $review['reviewReply']['updateTime'] ?? null,
            'reviewUrl' => (string)($review['reviewLink'] ?? $review['name'] ?? ''),
            'source' => 'Google',
            'isImported' => true,
        ];
    }

    private function normalizeStarRating(mixed $value): int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return max(0, min(5, (int)$value));
        }

        $map = [
            'ONE' => 1,
            'TWO' => 2,
            'THREE' => 3,
            'FOUR' => 4,
            'FIVE' => 5,
            'ONE_STAR' => 1,
            'TWO_STARS' => 2,
            'THREE_STARS' => 3,
            'FOUR_STARS' => 4,
            'FIVE_STARS' => 5,
        ];

        $key = strtoupper((string)$value);
        return $map[$key] ?? 0;
    }

    /**
     * @param array<string, mixed> $normalizedReview
     */
    private function passesFilters(array $normalizedReview): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($settings->minimumRating === null) {
            return true;
        }

        return (int)$normalizedReview['rating'] >= $settings->minimumRating;
    }

    /**
     * @param array<string, mixed> $normalizedReview
     */
    private function upsertReviewEntry(array $normalizedReview): void
    {
        $reviewId = (string)($normalizedReview['googleReviewId'] ?? '');
        if ($reviewId === '') {
            throw new RuntimeException('Cannot upsert review without googleReviewId.');
        }

        $review = GoogleReview::find()
            ->googleReviewId($reviewId)
            ->status(null)
            ->trashed(null)
            ->one();

        if (!$review instanceof GoogleReview) {
            $review = new GoogleReview();
        } elseif ($review->trashed) {
            Craft::$app->getElements()->restoreElement($review);
        }

        $author = (string)($normalizedReview['authorName'] ?? 'Anonymous');
        $rating = (int)($normalizedReview['rating'] ?? 0);
        $review->googleReviewId = $reviewId;
        $review->title = sprintf('%s - %d/5', $author, $rating);
        $review->slug = StringHelper::toKebabCase('google-review-' . $reviewId);
        $review->enabled = true;
        $review->authorName = $author;
        $review->authorPhotoUrl = (string)($normalizedReview['authorPhotoUrl'] ?? '');
        $review->rating = $rating;
        $review->reviewText = (string)($normalizedReview['reviewText'] ?? '');
        $review->replyText = (string)($normalizedReview['replyText'] ?? '');
        $review->reviewUrl = (string)($normalizedReview['reviewUrl'] ?? '');
        $review->source = (string)($normalizedReview['source'] ?? 'Google');
        $review->isImported = (bool)($normalizedReview['isImported'] ?? true);

        if (!empty($normalizedReview['reviewDate'])) {
            try {
                $review->reviewDate = new DateTime((string)$normalizedReview['reviewDate']);
            } catch (Throwable) {
                $review->reviewDate = null;
            }
        }

        if (!empty($normalizedReview['replyUpdatedAt'])) {
            try {
                $review->replyUpdatedAt = new DateTime((string)$normalizedReview['replyUpdatedAt']);
            } catch (Throwable) {
                $review->replyUpdatedAt = null;
            }
        } else {
            $review->replyUpdatedAt = null;
        }

        if (!Craft::$app->getElements()->saveElement($review)) {
            $errors = $review->getErrorSummary(true);
            throw new RuntimeException('Failed saving review element: ' . implode('; ', $errors));
        }
    }

    private function fetchAccessToken(string $clientId, string $clientSecret, string $refreshToken): string
    {
        $client = Craft::createGuzzleClient();
        $response = $client->request('POST', 'https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ],
            'http_errors' => false,
            'timeout' => 20,
        ]);

        $statusCode = $response->getStatusCode();
        $body = json_decode((string)$response->getBody(), true);

        if ($statusCode >= 400 || !is_array($body) || empty($body['access_token'])) {
            $error = is_array($body) ? json_encode($body) : (string)$response->getBody();
            throw new RuntimeException('OAuth token refresh failed (' . $statusCode . '): ' . $error);
        }

        return (string)$body['access_token'];
    }

    private function normalizeResourceName(string $value, string $prefix): string
    {
        $value = trim($value);
        if (str_contains($value, '/')) {
            return trim($value, '/');
        }

        return $prefix . '/' . $value;
    }
}
