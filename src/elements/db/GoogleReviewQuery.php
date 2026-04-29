<?php

namespace splendidweb\googlereviews\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

class GoogleReviewQuery extends ElementQuery
{
    public mixed $googleReviewId = null;
    public mixed $rating = null;
    public mixed $source = null;
    public mixed $sourceLocationId = null;
    public mixed $sourceLocationName = null;
    public mixed $featured = null;

    public function googleReviewId(mixed $value): self
    {
        $this->googleReviewId = $value;
        return $this;
    }

    public function rating(mixed $value): self
    {
        $this->rating = $value;
        return $this;
    }

    public function source(mixed $value): self
    {
        $this->source = $value;
        return $this;
    }

    public function sourceLocationId(mixed $value): self
    {
        $this->sourceLocationId = $value;
        return $this;
    }

    public function sourceLocationName(mixed $value): self
    {
        $this->sourceLocationName = $value;
        return $this;
    }

    public function featured(mixed $value = true): self
    {
        $this->featured = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('googlereviews_reviews');
        $this->query->select([
            'googlereviews_reviews.googleReviewId',
            'googlereviews_reviews.authorName',
            'googlereviews_reviews.authorPhotoUrl',
            'googlereviews_reviews.rating',
            'googlereviews_reviews.reviewText',
            'googlereviews_reviews.reviewDate',
            'googlereviews_reviews.replyText',
            'googlereviews_reviews.replyUpdatedAt',
            'googlereviews_reviews.reviewUrl',
            'googlereviews_reviews.sourceLocationId',
            'googlereviews_reviews.sourceLocationName',
            'googlereviews_reviews.source',
            'googlereviews_reviews.isImported',
            'googlereviews_reviews.featured',
        ]);

        if ($this->googleReviewId !== null) {
            $this->subQuery->andWhere(Db::parseParam('googlereviews_reviews.googleReviewId', $this->googleReviewId));
        }

        if ($this->rating !== null) {
            $this->subQuery->andWhere(Db::parseParam('googlereviews_reviews.rating', $this->rating));
        }

        if ($this->source !== null) {
            $this->subQuery->andWhere(Db::parseParam('googlereviews_reviews.source', $this->source));
        }

        if ($this->sourceLocationId !== null) {
            $this->subQuery->andWhere(Db::parseParam('googlereviews_reviews.sourceLocationId', $this->sourceLocationId));
        }

        if ($this->sourceLocationName !== null) {
            $this->subQuery->andWhere(Db::parseParam('googlereviews_reviews.sourceLocationName', $this->sourceLocationName));
        }

        if ($this->featured !== null) {
            $this->subQuery->andWhere(Db::parseBooleanParam('googlereviews_reviews.featured', $this->featured, false));
        }

        return parent::beforePrepare();
    }
}
