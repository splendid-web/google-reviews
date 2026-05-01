<?php

namespace splendidweb\googlereviews\variables;

use craft\db\Query;
use splendidweb\googlereviews\elements\GoogleReview;
use splendidweb\googlereviews\elements\db\GoogleReviewQuery;
use splendidweb\googlereviews\Plugin;

class GoogleReviewsVariable
{
    /**
     * @return array{sourceLocationId: string|null, sourceLocationName: string|null, sourceMode: string, overallRating: float|null, totalReviewCount: int|null, googlePlaceId: string|null, lastSyncedAt: string|null}
     */
    public function summary(?string $sourceLocationId = null): array
    {
        $locationId = trim((string)$sourceLocationId);
        if ($locationId === '') {
            $locationId = '';
        }

        $candidates = $this->locationSummaryCandidates($locationId);
        $row = null;
        foreach ($candidates as $candidate) {
            $found = (new Query())
                ->from('{{%googlereviews_summary}}')
                ->where(['sourceLocationId' => $candidate])
                ->one();
            if (is_array($found)) {
                $row = $found;
                break;
            }
        }

        if (!is_array($row)) {
            $settings = Plugin::getInstance()->getSettings();
            return [
                'sourceLocationId' => $locationId !== '' ? $locationId : null,
                'sourceLocationName' => null,
                'sourceMode' => (string)$settings->syncSourceMode,
                'overallRating' => null,
                'totalReviewCount' => null,
                'googlePlaceId' => null,
                'lastSyncedAt' => null,
            ];
        }

        return [
            'sourceLocationId' => isset($row['sourceLocationId']) && $row['sourceLocationId'] !== '' ? (string)$row['sourceLocationId'] : null,
            'sourceLocationName' => isset($row['sourceLocationName']) && $row['sourceLocationName'] !== '' ? (string)$row['sourceLocationName'] : null,
            'sourceMode' => (string)($row['sourceMode'] ?? 'mock'),
            'overallRating' => isset($row['overallRating']) ? (float)$row['overallRating'] : null,
            'totalReviewCount' => isset($row['totalReviewCount']) ? (int)$row['totalReviewCount'] : null,
            'googlePlaceId' => !empty($row['googlePlaceId']) ? (string)$row['googlePlaceId'] : null,
            'lastSyncedAt' => !empty($row['lastSyncedAt']) ? (string)$row['lastSyncedAt'] : null,
        ];
    }

    /**
     * @return string[]
     */
    private function locationSummaryCandidates(string $locationId): array
    {
        if ($locationId === '') {
            return [''];
        }

        $candidates = [$locationId];
        if (!str_contains($locationId, '/')) {
            $candidates[] = 'locations/' . $locationId;
            $candidates[] = 'places/' . $locationId;
        } else {
            $bare = basename(trim($locationId, '/'));
            if ($bare !== '' && $bare !== $locationId) {
                $candidates[] = $bare;
            }
        }

        return array_values(array_unique($candidates));
    }

    public function entries(
        int $limit = 10,
        ?int $minimumRating = null,
        ?string $sourceLocationId = null,
        ?string $sourceLocationName = null
    ): GoogleReviewQuery
    {
        return $this->reviews($limit, $minimumRating, $sourceLocationId, $sourceLocationName);
    }

    public function reviews(
        int $limit = 10,
        ?int $minimumRating = null,
        ?string $sourceLocationId = null,
        ?string $sourceLocationName = null
    ): GoogleReviewQuery
    {
        $query = GoogleReview::find()
            ->limit($limit)
            ->orderBy(['googlereviews_reviews.reviewDate' => SORT_DESC]);

        if ($minimumRating !== null) {
            $query->rating($minimumRating . '..5');
        }

        if ($sourceLocationId !== null && trim($sourceLocationId) !== '') {
            $query->sourceLocationId(trim($sourceLocationId));
        }

        if ($sourceLocationName !== null && trim($sourceLocationName) !== '') {
            $query->sourceLocationName(trim($sourceLocationName));
        }

        return $query;
    }
}
