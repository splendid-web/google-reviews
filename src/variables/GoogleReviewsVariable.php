<?php

namespace splendidweb\googlereviews\variables;

use splendidweb\googlereviews\elements\GoogleReview;
use splendidweb\googlereviews\elements\db\GoogleReviewQuery;

class GoogleReviewsVariable
{
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
