<?php

namespace splendidweb\googlereviews\models;

use craft\helpers\App;
use craft\base\Model;

class Settings extends Model
{
    public bool $enabled = true;
    public bool $useMockData = true;
    public string $googleAccountId = '';
    public string $googleLocationId = '';
    public string $oauthClientId = '';
    public string $oauthClientSecret = '';
    public string $oauthRefreshToken = '';
    public string $apiBaseUrl = 'https://mybusiness.googleapis.com/v4';
    public int $maxReviews = 50;
    public ?int $minimumRating = null;
    public string $attributionText = 'Reviews from Google';
    public string $attributionUrl = 'https://www.google.com/maps';

    public function rules(): array
    {
        return [
            [['enabled', 'useMockData'], 'boolean'],
            [['googleAccountId', 'googleLocationId', 'oauthClientId', 'oauthClientSecret', 'oauthRefreshToken', 'apiBaseUrl', 'attributionText', 'attributionUrl'], 'string'],
            [['maxReviews'], 'integer', 'min' => 1, 'max' => 500],
            [['minimumRating'], 'integer', 'min' => 1, 'max' => 5],
            [['apiBaseUrl'], 'url'],
            [['attributionUrl'], 'url'],
        ];
    }

    public function getParsedOAuthClientId(): string
    {
        return App::parseEnv($this->oauthClientId);
    }

    public function getParsedOAuthClientSecret(): string
    {
        return App::parseEnv($this->oauthClientSecret);
    }

    public function getParsedOAuthRefreshToken(): string
    {
        return App::parseEnv($this->oauthRefreshToken);
    }

    public function getParsedGoogleAccountId(): string
    {
        return App::parseEnv($this->googleAccountId);
    }

    public function getParsedGoogleLocationId(): string
    {
        return App::parseEnv($this->googleLocationId);
    }

    public function getParsedApiBaseUrl(): string
    {
        return App::parseEnv($this->apiBaseUrl);
    }
}
