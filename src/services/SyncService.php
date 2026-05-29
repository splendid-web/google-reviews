<?php

namespace splendidweb\googlereviews\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db as DbHelper;
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
    private const GBP_INFO_API_BASE_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1';
    private const GOOGLE_OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

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

            $syncPayload = $this->fetchReviewsWithSummary();
            $this->saveSummaries($syncPayload['summaries']);

            $rawReviews = $syncPayload['reviews'];
            $result->fetched = count($rawReviews);

            $syncedIds = [];
            foreach ($rawReviews as $rawReview) {
                $normalized = $this->normalizeReview($rawReview);

                if (!$this->passesFilters($normalized)) {
                    $result->skipped++;
                    continue;
                }

                $this->upsertReviewEntry($normalized);
                $syncedIds[] = (string)$normalized['googleReviewId'];
                $result->upserted++;
            }

            if (!$settings->isMockMode() && $syncedIds !== []) {
                $result->archived += $this->removeStaleReviews($syncedIds);
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
     * @param string[] $syncedIds
     */
    private function removeStaleReviews(array $syncedIds): int
    {
        $staleReviews = GoogleReview::find()
            ->status(null)
            ->source('Google')
            ->andWhere(['googlereviews_reviews.isImported' => true])
            ->andWhere(['not like', 'googlereviews_reviews.googleReviewId', 'mock-%', false])
            ->andWhere(['not in', 'googlereviews_reviews.googleReviewId', $syncedIds])
            ->all();

        $removed = 0;
        foreach ($staleReviews as $staleReview) {
            if (!$staleReview instanceof GoogleReview) {
                continue;
            }

            if (Craft::$app->getElements()->deleteElement($staleReview)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return array{reviews: array<int, array<string, mixed>>, summaries: array<int, array<string, mixed>>}
     */
    private function fetchReviewsWithSummary(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $perLocationMax = max(1, (int)$settings->maxReviews);

        if ($settings->isMockMode()) {
            $reviews = array_slice($this->mockReviews(), 0, min(3, $perLocationMax));
            return [
                'reviews' => $reviews,
                'summaries' => [
                    $this->buildSummary(
                        $this->calculateAverageRating($reviews),
                        count($reviews),
                        null,
                        '',
                        'All Locations'
                    ),
                ],
            ];
        }

        if ($settings->isPlacesMode()) {
            $placeIds = $settings->getParsedPlacesPlaceIds();
            if ($placeIds === []) {
                throw new RuntimeException('At least one Place ID is required when Places mode is enabled.');
            }

            $reviews = [];
            $weightedTotal = 0.0;
            $weightedCount = 0;
            $totalReviewCount = 0;
            $locationSummaries = [];

            foreach ($placeIds as $placeId) {
                $payload = $this->fetchPlacesReviewsWithSummary($placeId, $perLocationMax);
                $reviews = array_merge($reviews, $payload['reviews']);
                $locationSummaries[] = $payload['summary'];

                $locationRating = $payload['summary']['overallRating'] ?? null;
                $locationCount = $payload['summary']['totalReviewCount'] ?? null;
                if (is_numeric($locationRating) && is_numeric($locationCount) && (int)$locationCount > 0) {
                    $weightedTotal += ((float)$locationRating) * (int)$locationCount;
                    $weightedCount += (int)$locationCount;
                    $totalReviewCount += (int)$locationCount;
                }
            }

            $overallRating = $weightedCount > 0 ? $weightedTotal / $weightedCount : $this->calculateAverageRating($reviews);
            if ($weightedCount === 0) {
                $totalReviewCount = count($reviews);
            }

            $locationSummaries[] = $this->buildSummary($overallRating, $totalReviewCount, null, '', 'All Locations');

            return [
                'reviews' => $reviews,
                'summaries' => $locationSummaries,
            ];
        }

        $locationIds = $settings->getParsedGoogleLocationIds();
        if ($locationIds === []) {
            throw new RuntimeException('At least one Google location ID is required when Business Profile mode is selected.');
        }

        $reviews = [];
        $weightedTotal = 0.0;
        $weightedCount = 0;
        $totalReviewCount = 0;
        $locationSummaries = [];

        foreach ($locationIds as $locationId) {
            $payload = $this->fetchBusinessProfileReviewsWithSummary($locationId, $perLocationMax);
            $reviews = array_merge($reviews, $payload['reviews']);
            $locationSummaries[] = $payload['summary'];

            $locationRating = $payload['summary']['overallRating'] ?? null;
            $locationCount = $payload['summary']['totalReviewCount'] ?? null;
            if (is_numeric($locationRating) && is_numeric($locationCount) && (int)$locationCount > 0) {
                $weightedTotal += ((float)$locationRating) * (int)$locationCount;
                $weightedCount += (int)$locationCount;
                $totalReviewCount += (int)$locationCount;
            }
        }

        $overallRating = $weightedCount > 0 ? $weightedTotal / $weightedCount : $this->calculateAverageRating($reviews);
        if ($weightedCount === 0) {
            $totalReviewCount = count($reviews);
        }

        $locationSummaries[] = $this->buildSummary($overallRating, $totalReviewCount, null, '', 'All Locations');

        return [
            'reviews' => $reviews,
            'summaries' => $locationSummaries,
        ];
    }

    /**
     * @return array{reviews: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function fetchBusinessProfileReviewsWithSummary(string $locationId, int $max): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $accountId = trim($settings->getParsedGoogleAccountId());
        $clientId = trim($settings->getParsedOAuthClientId());
        $clientSecret = trim($settings->getParsedOAuthClientSecret());
        $refreshToken = trim($settings->getParsedOAuthRefreshToken());

        if ($accountId === '' || trim($locationId) === '') {
            throw new RuntimeException('Google account ID and location ID are required when Business Profile mode is selected.');
        }

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new RuntimeException('OAuth client ID, client secret, and refresh token are required when Business Profile mode is selected.');
        }

        $accessToken = $this->fetchAccessToken($clientId, $clientSecret, $refreshToken);
        $accountResource = $this->normalizeResourceName($accountId, 'accounts');
        $locationResource = $this->normalizeResourceName($locationId, 'locations');
        $locationDisplayName = $this->fetchBusinessProfileLocationName($accountResource, $locationResource, $accessToken);
        $endpoint = sprintf('%s/%s/%s/reviews', self::GBP_API_BASE_URL, $accountResource, $locationResource);

        $reviews = [];
        $overallRating = null;
        $totalReviewCount = null;
        $pageToken = null;
        $client = Craft::createGuzzleClient();

        do {
            $query = ['pageSize' => min(50, max(1, $max))];
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

            foreach ($batch as $review) {
                if (!is_array($review)) {
                    continue;
                }

                $review['_sourceLocationId'] = $locationResource;
                $review['_sourceLocationName'] = $locationDisplayName;
                $reviews[] = $review;
            }

            if ($overallRating === null) {
                $averageRating = $body['averageRating'] ?? $body['avgRating'] ?? null;
                if (is_numeric($averageRating)) {
                    $overallRating = (float)$averageRating;
                }
            }
            if ($totalReviewCount === null && isset($body['totalReviewCount']) && is_numeric($body['totalReviewCount'])) {
                $totalReviewCount = (int)$body['totalReviewCount'];
            }

            $pageToken = $body['nextPageToken'] ?? null;
        } while ($pageToken && count($reviews) < $max);

        $reviews = array_slice($reviews, 0, $max);

        return [
            'reviews' => $reviews,
            'summary' => $this->buildSummary(
                $overallRating,
                $totalReviewCount,
                null,
                $locationResource,
                $locationDisplayName
            ),
        ];
    }

    private function fetchBusinessProfileLocationName(string $accountResource, string $locationResource, string $accessToken): string
    {
        $locationId = basename(trim($locationResource, '/'));
        $fallback = $locationResource;
        if ($locationId === '') {
            return $fallback;
        }

        $client = Craft::createGuzzleClient();
        $endpoints = [
            sprintf('%s/%s/locations/%s', self::GBP_INFO_API_BASE_URL, $accountResource, $locationId),
            sprintf('%s/%s', self::GBP_INFO_API_BASE_URL, trim($locationResource, '/')),
        ];

        foreach ($endpoints as $endpoint) {
            $response = $client->request('GET', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'readMask' => 'title',
                ],
                'http_errors' => false,
                'timeout' => 20,
            ]);

            $body = json_decode((string)$response->getBody(), true);
            if ($response->getStatusCode() >= 400 || !is_array($body)) {
                continue;
            }

            $title = isset($body['title']) && is_string($body['title']) ? trim($body['title']) : '';
            if ($title !== '') {
                return $title;
            }
        }

        return $fallback;
    }

    /**
     * @return array{reviews: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function fetchPlacesReviewsWithSummary(string $placeId, int $max): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $apiKey = trim($settings->getParsedPlacesApiKey());
        $placeId = trim($placeId);

        if ($apiKey === '' || $placeId === '') {
            throw new RuntimeException('Places API key and Place ID are required when Places mode is enabled.');
        }

        $placeResource = str_starts_with($placeId, 'places/') ? $placeId : 'places/' . $placeId;

        $client = Craft::createGuzzleClient();
        $response = $client->request('GET', 'https://places.googleapis.com/v1/' . $placeResource, [
            'headers' => [
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'id,displayName,rating,userRatingCount,reviews,googleMapsUri',
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

        $locationId = (string)($body['id'] ?? $placeId);
        $displayName = $body['displayName'] ?? null;
        if (is_array($displayName)) {
            $locationName = (string)($displayName['text'] ?? $locationId);
        } elseif (is_string($displayName) && trim($displayName) !== '') {
            $locationName = trim($displayName);
        } else {
            $locationName = $locationId;
        }
        $placeUrl = (string)($body['googleMapsUri'] ?? '');
        $normalizedRaw = [];
        foreach ($reviews as $review) {
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

            $rawId = $authorUri !== ''
                ? $authorUri . '|' . $publishTime
                : md5($publishTime . '|' . $reviewText);
            $normalizedRaw[] = [
                'reviewId' => 'places-' . md5($rawId),
                '_sourceLocationId' => $locationId,
                '_sourceLocationName' => $locationName,
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

        return [
            'reviews' => array_slice($normalizedRaw, 0, $max),
            'summary' => $this->buildSummary(
                isset($body['rating']) && is_numeric($body['rating']) ? (float)$body['rating'] : null,
                isset($body['userRatingCount']) && is_numeric($body['userRatingCount']) ? (int)$body['userRatingCount'] : null,
                (string)($body['id'] ?? $placeId),
                $locationId,
                $locationName
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummary(
        ?float $overallRating,
        ?int $totalReviewCount,
        ?string $googlePlaceId,
        string $sourceLocationId = '',
        string $sourceLocationName = ''
    ): array
    {
        $settings = Plugin::getInstance()->getSettings();

        return [
            'sourceLocationId' => trim($sourceLocationId),
            'sourceLocationName' => trim($sourceLocationName),
            'sourceMode' => $settings->syncSourceMode,
            'overallRating' => $overallRating !== null ? round($overallRating, 2) : null,
            'totalReviewCount' => $totalReviewCount,
            'googlePlaceId' => $googlePlaceId,
            'lastSyncedAt' => new DateTime(),
        ];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function saveSummaries(array $summaries): void
    {
        foreach ($summaries as $summary) {
            if (!is_array($summary)) {
                continue;
            }

            $sourceLocationId = trim((string)($summary['sourceLocationId'] ?? ''));
            $sourceLocationName = trim((string)($summary['sourceLocationName'] ?? ''));

            if ($sourceLocationId === '' && $sourceLocationName === '') {
                $sourceLocationName = 'All Locations';
            }

            $payload = [
                'sourceLocationId' => $sourceLocationId,
                'sourceLocationName' => $sourceLocationName,
                'sourceMode' => (string)($summary['sourceMode'] ?? 'mock'),
                'overallRating' => isset($summary['overallRating']) ? (float)$summary['overallRating'] : null,
                'totalReviewCount' => isset($summary['totalReviewCount']) ? (int)$summary['totalReviewCount'] : null,
                'googlePlaceId' => isset($summary['googlePlaceId']) ? (string)$summary['googlePlaceId'] : null,
                'lastSyncedAt' => DbHelper::prepareDateForDb($summary['lastSyncedAt'] ?? new DateTime()),
            ];

            DbHelper::upsert(
                '{{%googlereviews_summary}}',
                $payload,
                $payload
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $reviews
     */
    private function calculateAverageRating(array $reviews): ?float
    {
        if ($reviews === []) {
            return null;
        }

        $total = 0;
        foreach ($reviews as $review) {
            $total += $this->normalizeStarRating($review['starRating'] ?? 0);
        }

        return $total / count($reviews);
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
        if ($reviewId === '') {
            $reviewId = md5(json_encode([
                $review['reviewer']['displayName'] ?? '',
                $review['createTime'] ?? '',
                $review['comment'] ?? '',
                $review['reviewLink'] ?? '',
            ]));
        }

        $sourceLocationId = (string)($review['_sourceLocationId'] ?? '');
        $sourceLocationName = (string)($review['_sourceLocationName'] ?? $sourceLocationId);
        $scopedReviewId = $sourceLocationId !== ''
            ? md5($sourceLocationId . '::' . $reviewId)
            : $reviewId;

        return [
            'googleReviewId' => $scopedReviewId,
            'authorName' => (string)($review['reviewer']['displayName'] ?? ''),
            'authorPhotoUrl' => (string)($review['reviewer']['profilePhotoUrl'] ?? ''),
            'rating' => $this->normalizeStarRating($review['starRating'] ?? 0),
            'reviewText' => (string)($review['comment'] ?? ''),
            'reviewDate' => $review['createTime'] ?? null,
            'replyText' => (string)($review['reviewReply']['comment'] ?? ''),
            'replyUpdatedAt' => $review['reviewReply']['updateTime'] ?? null,
            'reviewUrl' => (string)($review['reviewLink'] ?? $review['name'] ?? ''),
            'sourceLocationId' => $sourceLocationId,
            'sourceLocationName' => $sourceLocationName,
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
        $review->sourceLocationId = (string)($normalizedReview['sourceLocationId'] ?? '');
        $review->sourceLocationName = (string)($normalizedReview['sourceLocationName'] ?? '');
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

    public function fetchAccessToken(string $clientId, string $clientSecret, string $refreshToken): string
    {
        $client = Craft::createGuzzleClient();
        $response = $client->request('POST', self::GOOGLE_OAUTH_TOKEN_URL, [
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

    /**
     * @return array{refreshToken: string|null, accessToken: string, expiresIn: int|null}
     */
    public function exchangeAuthorizationCode(
        string $clientId,
        string $clientSecret,
        string $authorizationCode,
        string $redirectUri
    ): array {
        $client = Craft::createGuzzleClient();
        $response = $client->request('POST', self::GOOGLE_OAUTH_TOKEN_URL, [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ],
            'http_errors' => false,
            'timeout' => 20,
        ]);

        $statusCode = $response->getStatusCode();
        $body = json_decode((string)$response->getBody(), true);

        if ($statusCode >= 400 || !is_array($body) || empty($body['access_token'])) {
            $error = is_array($body) ? json_encode($body) : (string)$response->getBody();
            throw new RuntimeException('OAuth authorization code exchange failed (' . $statusCode . '): ' . $error);
        }

        $refreshToken = isset($body['refresh_token']) && is_string($body['refresh_token'])
            ? trim($body['refresh_token'])
            : null;

        return [
            'refreshToken' => $refreshToken !== '' ? $refreshToken : null,
            'accessToken' => (string)$body['access_token'],
            'expiresIn' => isset($body['expires_in']) && is_numeric($body['expires_in']) ? (int)$body['expires_in'] : null,
        ];
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
