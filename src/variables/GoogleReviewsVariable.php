<?php

namespace splendidweb\googlereviews\variables;

use craft\db\Query;
use splendidweb\googlereviews\elements\GoogleReview;
use splendidweb\googlereviews\elements\db\GoogleReviewQuery;

class GoogleReviewsVariable
{
    /**
     * @return array{sourceMode: string, overallRating: float|null, totalReviewCount: int|null, googlePlaceId: string|null, lastSyncedAt: string|null}
     */
    public function summary(): array
    {
        $row = (new Query())
            ->from('{{%googlereviews_summary}}')
            ->where(['id' => 1])
            ->one();

        if (!is_array($row)) {
            return [
                'sourceMode' => 'mock',
                'overallRating' => null,
                'totalReviewCount' => null,
                'googlePlaceId' => null,
                'lastSyncedAt' => null,
            ];
        }

        return [
            'sourceMode' => (string)($row['sourceMode'] ?? 'mock'),
            'overallRating' => isset($row['overallRating']) ? (float)$row['overallRating'] : null,
            'totalReviewCount' => isset($row['totalReviewCount']) ? (int)$row['totalReviewCount'] : null,
            'googlePlaceId' => !empty($row['googlePlaceId']) ? (string)$row['googlePlaceId'] : null,
            'lastSyncedAt' => !empty($row['lastSyncedAt']) ? (string)$row['lastSyncedAt'] : null,
        ];
    }

    public function entries(int $limit = 10, ?int $minimumRating = null): GoogleReviewQuery
    {
        return $this->reviews($limit, $minimumRating);
    }

    public function reviews(int $limit = 10, ?int $minimumRating = null): GoogleReviewQuery
    {
        $query = GoogleReview::find()
            ->limit($limit)
            ->orderBy(['googlereviews_reviews.reviewDate' => SORT_DESC]);

        if ($minimumRating !== null) {
            $query->rating($minimumRating . '..5');
        }

        return $query;
    }
}
