<?php

namespace splendidweb\googlereviews\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use splendidweb\googlereviews\models\SyncResult;
use splendidweb\googlereviews\Plugin;
use DateTime;
use RuntimeException;
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
        $settings = Plugin::getInstance()->getSettings();
        $max = max(1, min(3, $settings->maxReviews));

        $mock = [
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

        return array_slice($mock, 0, $max);
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
            'reviewUrl' => (string)($review['reviewLink'] ?? ''),
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
        $entriesService = Craft::$app->getEntries();
        $section = $entriesService->getSectionByHandle('googleReviews');

        if ($section === null) {
            throw new RuntimeException('Section "googleReviews" is missing. Install/update the plugin migrations first.');
        }

        $entryType = $entriesService->getEntryTypeByHandle('googleReview');
        if ($entryType === null) {
            $sectionEntryTypes = $entriesService->getEntryTypesBySectionId($section->id);
            $entryType = $sectionEntryTypes[0] ?? null;
        }

        if ($entryType === null) {
            throw new RuntimeException('Entry type for section "googleReviews" is missing.');
        }

        $reviewId = (string)($normalizedReview['googleReviewId'] ?? '');
        if ($reviewId === '') {
            throw new RuntimeException('Cannot upsert review without googleReviewId.');
        }

        $slug = 'google-review-' . strtolower(preg_replace('/[^a-z0-9]+/', '-', $reviewId));
        $entry = Entry::find()
            ->section('googleReviews')
            ->slug($slug)
            ->status(null)
            ->site('*')
            ->one();

        if (!$entry instanceof Entry) {
            $entry = new Entry();
            $entry->sectionId = $section->id;
            $entry->typeId = $entryType->id;
        }

        $author = (string)($normalizedReview['authorName'] ?? 'Anonymous');
        $rating = (int)($normalizedReview['rating'] ?? 0);
        $entry->title = sprintf('%s - %d/5', $author, $rating);
        $entry->slug = $slug;
        $entry->enabled = true;

        if (!empty($normalizedReview['reviewDate'])) {
            try {
                $entry->postDate = new DateTime((string)$normalizedReview['reviewDate']);
            } catch (Throwable) {
                $entry->postDate = new DateTime();
            }
        } else {
            $entry->postDate = new DateTime();
        }

        if (!Craft::$app->getElements()->saveElement($entry)) {
            $errors = $entry->getErrorSummary(true);
            throw new RuntimeException('Failed saving review entry: ' . implode('; ', $errors));
        }
    }
}
