<?php

declare(strict_types=1);

namespace splendidweb\googlereviews\tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use splendidweb\googlereviews\models\Settings;

final class SettingsTest extends TestCase
{
    public function testDefaultModeHelpers(): void
    {
        $settings = new Settings();

        self::assertTrue($settings->isMockMode());
        self::assertFalse($settings->isPlacesMode());
        self::assertFalse($settings->isBusinessProfileMode());
    }

    public function testModeHelpersFollowSyncSourceMode(): void
    {
        $settings = new Settings();
        $settings->syncSourceMode = Settings::MODE_PLACES;

        self::assertFalse($settings->isMockMode());
        self::assertTrue($settings->isPlacesMode());
        self::assertFalse($settings->isBusinessProfileMode());

        $settings->syncSourceMode = Settings::MODE_BUSINESS_PROFILE;

        self::assertTrue($settings->isBusinessProfileMode());
    }

    public function testEmptyLocationIdReturnsNoPairs(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = '111';
        $settings->googleLocationId = '';

        self::assertSame([], $settings->getParsedGoogleLocationPairs());
        self::assertSame([], $settings->getParsedGoogleLocationIds());
    }

    public function testSingleLocationUsesDefaultAccount(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = '111';
        $settings->googleLocationId = '222';

        self::assertSame([
            ['accountId' => '111', 'locationId' => '222'],
        ], $settings->getParsedGoogleLocationPairs());
        self::assertSame(['222'], $settings->getParsedGoogleLocationIds());
    }

    public function testSingleLocationWithoutAccountReturnsEmpty(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = '';
        $settings->googleLocationId = '222';

        self::assertSame([], $settings->getParsedGoogleLocationPairs());
    }

    public function testJsonArrayOfLocationIdsUsesDefaultAccount(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = '111';
        $settings->googleLocationId = '["222","333"]';

        self::assertSame([
            ['accountId' => '111', 'locationId' => '222'],
            ['accountId' => '111', 'locationId' => '333'],
        ], $settings->getParsedGoogleLocationPairs());
        self::assertSame(['222', '333'], $settings->getParsedGoogleLocationIds());
    }

    public function testJsonArrayOfAccountLocationPairs(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = '';
        $settings->googleLocationId = '[{"account":"111","location":"222"},{"account":"333","location":"444"}]';

        self::assertSame([
            ['accountId' => '111', 'locationId' => '222'],
            ['accountId' => '333', 'locationId' => '444'],
        ], $settings->getParsedGoogleLocationPairs());
    }

    public function testPairObjectAcceptsAccountIdAndLocationIdKeys(): void
    {
        $settings = new Settings();
        $settings->googleLocationId = '{"accountId":"111","locationId":"222"}';

        self::assertSame([
            ['accountId' => '111', 'locationId' => '222'],
        ], $settings->getParsedGoogleLocationPairs());
    }

    public function testPairFallsBackToDefaultAccountWhenAccountOmitted(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = '111';
        $settings->googleLocationId = '[{"location":"222"},{"account":"333","location":"444"}]';

        self::assertSame([
            ['accountId' => '111', 'locationId' => '222'],
            ['accountId' => '333', 'locationId' => '444'],
        ], $settings->getParsedGoogleLocationPairs());
    }

    public function testDuplicatePairsAreDeduplicated(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = '111';
        $settings->googleLocationId = '["222","222"]';

        self::assertSame([
            ['accountId' => '111', 'locationId' => '222'],
        ], $settings->getParsedGoogleLocationPairs());
    }

    public function testWhitespaceIsTrimmedOnPairs(): void
    {
        $settings = new Settings();
        $settings->googleAccountId = ' 111 ';
        $settings->googleLocationId = ' 222 ';

        self::assertSame([
            ['accountId' => '111', 'locationId' => '222'],
        ], $settings->getParsedGoogleLocationPairs());
    }

    #[DataProvider('placesPlaceIdsProvider')]
    public function testParsedPlacesPlaceIds(string $raw, array $expected): void
    {
        $settings = new Settings();
        $settings->placesPlaceId = $raw;

        self::assertSame($expected, $settings->getParsedPlacesPlaceIds());
    }

    /**
     * @return array<string, array{0: string, 1: string[]}>
     */
    public static function placesPlaceIdsProvider(): array
    {
        return [
            'empty' => ['', []],
            'single' => ['ChIJ123', ['ChIJ123']],
            'json array' => ['["ChIJ123","ChIJ456"]', ['ChIJ123', 'ChIJ456']],
            'deduped' => ['["ChIJ123","ChIJ123"]', ['ChIJ123']],
            'ignores non-strings' => ['["ChIJ123",123,null]', ['ChIJ123']],
        ];
    }
}
