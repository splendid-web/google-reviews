<?php

namespace splendidweb\googlereviews\services;

use Craft;
use craft\base\Component;
use splendidweb\googlereviews\models\SyncResult;
use splendidweb\googlereviews\Plugin;
use Throwable;

class SyncService extends Component
{
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

    /**
     * Stub for the Google Business Profile API integration.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchReviews(): array
    {
        // TODO: Replace with Google API client call.
        return [];
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>
     */
    private function normalizeReview(array $review): array
    {
        return [
            'googleReviewId' => (string)($review['reviewId'] ?? ''),
            'authorName' => (string)($review['reviewer']['displayName'] ?? ''),
            'authorPhotoUrl' => (string)($review['reviewer']['profilePhotoUrl'] ?? ''),
            'rating' => (int)($review['starRating'] ?? 0),
            'reviewText' => (string)($review['comment'] ?? ''),
            'reviewDate' => $review['createTime'] ?? null,
            'reviewUrl' => (string)($review['reviewReply']['reviewLink'] ?? ''),
            'source' => 'Google',
            'isImported' => true,
        ];
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
        // TODO: Upsert Craft entry by googleReviewId once section/fields are established.
        Craft::info(
            'Prepared review for upsert: ' . ($normalizedReview['googleReviewId'] ?? 'unknown'),
            __METHOD__
        );
    }
}
