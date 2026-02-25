<?php

namespace splendidweb\googlereviews\models;

use craft\helpers\App;
use craft\base\Model;

class Settings extends Model
{
    public bool $enabled = true;
    public string $googleAccountId = '';
    public string $googleLocationId = '';
    public string $credentialsPath = '';
    public int $maxReviews = 50;
    public ?int $minimumRating = null;
    public string $attributionText = 'Reviews from Google';
    public string $attributionUrl = 'https://www.google.com/maps';

    public function rules(): array
    {
        return [
            [['enabled'], 'boolean'],
            [['googleAccountId', 'googleLocationId', 'credentialsPath', 'attributionText', 'attributionUrl'], 'string'],
            [['maxReviews'], 'integer', 'min' => 1, 'max' => 500],
            [['minimumRating'], 'integer', 'min' => 1, 'max' => 5],
            [['attributionUrl'], 'url'],
        ];
    }

    public function getParsedCredentialsPath(): string
    {
        return App::parseEnv($this->credentialsPath);
    }
}
