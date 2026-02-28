<?php

namespace splendidweb\googlereviews\models;

use craft\helpers\App;
use craft\base\Model;

class Settings extends Model
{
    public bool $enabled = true;
    public bool $useMockData = true;
    public bool $usePlacesApi = false;
    public string $googleAccountId = '';
    public string $googleLocationId = '';
    public string $oauthClientId = '';
    public string $oauthClientSecret = '';
    public string $oauthRefreshToken = '';
    public string $placesApiKey = '';
    public string $placesPlaceId = '';
    public int $maxReviews = 50;
    public ?int $minimumRating = null;

    public function rules(): array
    {
        return [
            [['enabled', 'useMockData', 'usePlacesApi'], 'boolean'],
            [['googleAccountId', 'googleLocationId', 'oauthClientId', 'oauthClientSecret', 'oauthRefreshToken', 'placesApiKey', 'placesPlaceId'], 'string'],
            [['maxReviews'], 'integer', 'min' => 1, 'max' => 500],
            [['minimumRating'], 'integer', 'min' => 1, 'max' => 5],
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

    public function getParsedPlacesApiKey(): string
    {
        return App::parseEnv($this->placesApiKey);
    }

    public function getParsedPlacesPlaceId(): string
    {
        return App::parseEnv($this->placesPlaceId);
    }
}
