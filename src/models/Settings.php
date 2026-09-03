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

    /**
     * @return string[]
     */
    public function getParsedGoogleLocationIds(): array
    {
        $ids = [];
        foreach ($this->getParsedGoogleLocationPairs() as $pair) {
            $ids[] = $pair['locationId'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * Location IDs paired with the GBP account that owns them.
     *
     * Supports a single location ID, a JSON array of IDs (all using Account ID),
     * or a JSON array of {"account":"...","location":"..."} objects for multiple accounts.
     *
     * @return array<int, array{accountId: string, locationId: string}>
     */
    public function getParsedGoogleLocationPairs(): array
    {
        $parsed = trim(App::parseEnv($this->googleLocationId));
        $defaultAccount = trim($this->getParsedGoogleAccountId());

        if ($parsed === '') {
            return [];
        }

        $decoded = json_decode($parsed, true);
        if (!is_array($decoded)) {
            return $this->locationPair($defaultAccount, $parsed);
        }

        if ($this->isLocationPairItem($decoded)) {
            return $this->locationPairFromItem($decoded, $defaultAccount);
        }

        $pairs = [];
        foreach ($decoded as $item) {
            if ($this->isLocationPairItem($item)) {
                $pairs = array_merge($pairs, $this->locationPairFromItem($item, $defaultAccount));
                continue;
            }

            if (is_string($item)) {
                $pairs = array_merge($pairs, $this->locationPair($defaultAccount, $item));
            }
        }

        return $this->uniqueLocationPairs($pairs);
    }

    public function getParsedPlacesApiKey(): string
    {
        return App::parseEnv($this->placesApiKey);
    }

    public function getParsedPlacesPlaceId(): string
    {
        return App::parseEnv($this->placesPlaceId);
    }

    /**
     * @return string[]
     */
    public function getParsedPlacesPlaceIds(): array
    {
        return $this->parseEnvList($this->placesPlaceId);
    }

    /**
     * @return string[]
     */
    private function parseEnvList(string $rawValue): array
    {
        $parsed = trim(App::parseEnv($rawValue));
        if ($parsed === '') {
            return [];
        }

        $decoded = json_decode($parsed, true);
        if (is_array($decoded)) {
            $values = [];
            foreach ($decoded as $value) {
                if (!is_string($value)) {
                    continue;
                }

                $normalized = trim($value);
                if ($normalized !== '') {
                    $values[] = $normalized;
                }
            }

            return array_values(array_unique($values));
        }

        return [$parsed];
    }

    /**
     * @param mixed $item
     */
    private function isLocationPairItem(mixed $item): bool
    {
        if (!is_array($item) || array_is_list($item)) {
            return false;
        }

        $location = $item['location'] ?? $item['locationId'] ?? null;

        return is_string($location) && trim($location) !== '';
    }

    /**
     * @param array<string, mixed> $item
     * @return array<int, array{accountId: string, locationId: string}>
     */
    private function locationPairFromItem(array $item, string $defaultAccount): array
    {
        $locationId = trim((string)($item['location'] ?? $item['locationId'] ?? ''));
        $accountId = trim((string)($item['account'] ?? $item['accountId'] ?? $defaultAccount));

        return $this->locationPair($accountId, $locationId);
    }

    /**
     * @return array<int, array{accountId: string, locationId: string}>
     */
    private function locationPair(string $accountId, string $locationId): array
    {
        $accountId = trim($accountId);
        $locationId = trim($locationId);
        if ($accountId === '' || $locationId === '') {
            return [];
        }

        return [[
            'accountId' => $accountId,
            'locationId' => $locationId,
        ]];
    }

    /**
     * @param array<int, array{accountId: string, locationId: string}> $pairs
     * @return array<int, array{accountId: string, locationId: string}>
     */
    private function uniqueLocationPairs(array $pairs): array
    {
        $unique = [];
        foreach ($pairs as $pair) {
            $key = $pair['accountId'] . "\n" . $pair['locationId'];
            $unique[$key] = $pair;
        }

        return array_values($unique);
    }
}
