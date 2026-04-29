<?php

namespace splendidweb\googlereviews\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\Html;
use DateTime;
use splendidweb\googlereviews\elements\db\GoogleReviewQuery;

class GoogleReview extends Element
{
    public ?string $googleReviewId = null;
    public string $authorName = '';
    public string $authorPhotoUrl = '';
    public int $rating = 0;
    public string $reviewText = '';
    public ?DateTime $reviewDate = null;
    public string $replyText = '';
    public ?DateTime $replyUpdatedAt = null;
    public string $reviewUrl = '';
    public string $sourceLocationId = '';
    public string $sourceLocationName = '';
    public string $source = 'Google';
    public bool $isImported = true;
    public bool $featured = false;

    public static function displayName(): string
    {
        return 'Google Review';
    }

    public static function pluralDisplayName(): string
    {
        return 'Google Reviews';
    }

    public static function find(): GoogleReviewQuery
    {
        return new GoogleReviewQuery(static::class);
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasContent(): bool
    {
        return false;
    }

    public static function isLocalized(): bool
    {
        return false;
    }

    public function getAuthorPhoto(): string
    {
        return $this->authorPhotoUrl;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['googleReviewId'], 'required'];
        $rules[] = [['rating'], 'integer', 'min' => 0, 'max' => 5];
        return $rules;
    }

    public function canView(User $user): bool
    {
        return parent::canView($user) || $user->can('accessCp');
    }

    public function canSave(User $user): bool
    {
        return parent::canSave($user) || $user->can('accessCp');
    }

    public function canDelete(User $user): bool
    {
        return parent::canDelete($user) || $this->canSave($user);
    }

    public function canDeleteForSite(User $user): bool
    {
        return parent::canDeleteForSite($user) || $this->canDelete($user);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $payload = [
                'googleReviewId' => $this->googleReviewId,
                'authorName' => $this->authorName,
                'authorPhotoUrl' => $this->authorPhotoUrl,
                'rating' => $this->rating,
                'reviewText' => $this->reviewText,
                'reviewDate' => Db::prepareDateForDb($this->reviewDate),
                'replyText' => $this->replyText,
                'replyUpdatedAt' => Db::prepareDateForDb($this->replyUpdatedAt),
                'reviewUrl' => $this->reviewUrl,
                'sourceLocationId' => $this->sourceLocationId,
                'sourceLocationName' => $this->sourceLocationName,
                'source' => $this->source,
                'isImported' => $this->isImported,
                'featured' => $this->featured,
            ];

            Db::upsert('{{%googlereviews_reviews}}', array_merge([
                'id' => $this->id,
            ], $payload), $payload);
        }

        parent::afterSave($isNew);
    }

    protected static function defineSearchableAttributes(): array
    {
        return [
            'title',
            'googleReviewId',
            'authorName',
            'reviewText',
            'replyText',
            'sourceLocationId',
            'sourceLocationName',
            'source',
        ];
    }

    protected static function defineSources(string $context = null): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('google-reviews', 'All Google Reviews'),
                'criteria' => [],
                'defaultSort' => ['reviewDate', 'desc'],
            ],
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'authorName' => Craft::t('google-reviews', 'Author'),
            'authorPhoto' => Craft::t('google-reviews', 'Photo'),
            'sourceLocationName' => Craft::t('google-reviews', 'Location'),
            'rating' => Craft::t('google-reviews', 'Rating'),
            'reviewText' => Craft::t('google-reviews', 'Comment'),
            'replyText' => Craft::t('google-reviews', 'Reply'),
            'reviewDate' => Craft::t('google-reviews', 'Review Date'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['authorName', 'authorPhoto', 'sourceLocationName', 'rating', 'reviewText', 'replyText', 'reviewDate', 'dateUpdated'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'reviewDate' => Craft::t('google-reviews', 'Review Date'),
            'rating' => Craft::t('google-reviews', 'Rating'),
            'dateUpdated' => Craft::t('app', 'Date Updated'),
        ];
    }

    protected static function defineActions(string $source): array
    {
        return [
            Delete::class,
        ];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'authorName' => Craft::$app->getFormatter()->asText($this->authorName),
            'authorPhoto' => $this->authorPhotoUrl !== ''
                ? Html::img($this->authorPhotoUrl, [
                    'alt' => $this->authorName !== '' ? $this->authorName . ' profile photo' : 'Author profile photo',
                    'loading' => 'lazy',
                    'width' => 28,
                    'height' => 28,
                    'style' => 'border-radius: 50%; object-fit: cover;',
                ])
                : '—',
            'sourceLocationName' => Craft::$app->getFormatter()->asText($this->sourceLocationName !== '' ? $this->sourceLocationName : '—'),
            'rating' => Craft::$app->getFormatter()->asText((string)$this->rating),
            'reviewText' => Craft::$app->getFormatter()->asText(mb_strimwidth($this->reviewText, 0, 140, '...')),
            'replyText' => Craft::$app->getFormatter()->asText(mb_strimwidth($this->replyText, 0, 140, '...')),
            'reviewDate' => $this->reviewDate ? Craft::$app->getFormatter()->asDate($this->reviewDate) : '',
            'source' => Craft::$app->getFormatter()->asText($this->source),
            default => parent::tableAttributeHtml($attribute),
        };
    }
}
