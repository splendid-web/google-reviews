<?php

namespace splendidweb\googlereviews\models;

use craft\helpers\App;
use craft\base\Model;

class Settings extends Model
{
    public const MODE_MOCK = 'mock';
    public const MODE_PLACES = 'places';
    public const MODE_BUSINESS_PROFILE = 'businessProfile';

    public bool $enabled = true;
    public string $syncSourceMode = self::MODE_MOCK;
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
            [['enabled'], 'boolean'],
            [['syncSourceMode'], 'in', 'range' => [
                self::MODE_MOCK,
                self::MODE_PLACES,
                self::MODE_BUSINESS_PROFILE,
            ]],
            [['googleAccountId', 'googleLocationId', 'oauthClientId', 'oauthClientSecret', 'oauthRefreshToken', 'placesApiKey', 'placesPlaceId'], 'string'],
            [['maxReviews'], 'integer', 'min' => 1, 'max' => 500],
            [['minimumRating'], 'integer', 'min' => 1, 'max' => 5],
        ];
    }

    public function isMockMode(): bool
    {
        return $this->syncSourceMode === self::MODE_MOCK;
    }

    public function isPlacesMode(): bool
    {
        return $this->syncSourceMode === self::MODE_PLACES;
    }

    public function isBusinessProfileMode(): bool
    {
        return $this->syncSourceMode === self::MODE_BUSINESS_PROFILE;
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
