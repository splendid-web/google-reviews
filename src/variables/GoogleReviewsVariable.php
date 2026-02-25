<?php

namespace splendidweb\googlereviews\variables;

use craft\elements\Entry;
use craft\elements\db\EntryQuery;

class GoogleReviewsVariable
{
    public function entries(int $limit = 10, ?int $minimumRating = null): EntryQuery
    {
        $query = Entry::find()
            ->section('googleReviews')
            ->limit($limit)
            ->orderBy(['postDate' => SORT_DESC]);

        if ($minimumRating !== null) {
            $query->rating($minimumRating . '..5');
        }

        return $query;
    }
}
