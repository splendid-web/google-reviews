<?php

declare(strict_types=1);

namespace splendidweb\googlereviews\tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use splendidweb\googlereviews\services\SyncService;
use splendidweb\googlereviews\tests\Support\InvokesPrivateMethods;

final class SyncServiceHelpersTest extends TestCase
{
    use InvokesPrivateMethods;

    private SyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SyncService();
    }

    #[DataProvider('resourceNameProvider')]
    public function testNormalizeResourceName(string $value, string $prefix, string $expected): void
    {
        self::assertSame(
            $expected,
            $this->invokePrivate($this->service, 'normalizeResourceName', $value, $prefix)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function resourceNameProvider(): array
    {
        return [
            'bare id' => ['123456', 'locations', 'locations/123456'],
            'already prefixed' => ['locations/123456', 'locations', 'locations/123456'],
            'trims slashes' => ['/accounts/999/', 'accounts', 'accounts/999'],
            'whitespace' => [' 123 ', 'accounts', 'accounts/123'],
        ];
    }

    #[DataProvider('starRatingProvider')]
    public function testNormalizeStarRating(mixed $value, int $expected): void
    {
        self::assertSame(
            $expected,
            $this->invokePrivate($this->service, 'normalizeStarRating', $value)
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: int}>
     */
    public static function starRatingProvider(): array
    {
        return [
            'int' => [4, 4],
            'float' => [4.9, 4],
            'numeric string' => ['5', 5],
            'clamped high' => [9, 5],
            'clamped low' => [-2, 0],
            'enum five' => ['FIVE', 5],
            'enum four stars' => ['FOUR_STARS', 4],
            'unknown' => ['GREAT', 0],
        ];
    }

    public function testNormalizeReviewScopesIdByLocation(): void
    {
        $normalized = $this->invokePrivate($this->service, 'normalizeReview', [
            'reviewId' => 'abc123',
            '_sourceLocationId' => 'locations/999',
            '_sourceLocationName' => 'Hotel London',
            'reviewer' => [
                'displayName' => 'Alex',
                'profilePhotoUrl' => 'https://example.com/a.jpg',
            ],
            'starRating' => 'FIVE',
            'comment' => 'Lovely stay',
            'createTime' => '2026-02-12T10:30:00+00:00',
            'reviewLink' => 'https://maps.google.com/review',
            'reviewReply' => [
                'comment' => 'Thanks!',
                'updateTime' => '2026-02-13T10:30:00+00:00',
            ],
        ]);

        self::assertSame(md5('locations/999::abc123'), $normalized['googleReviewId']);
        self::assertSame('Alex', $normalized['authorName']);
        self::assertSame(5, $normalized['rating']);
        self::assertSame('Lovely stay', $normalized['reviewText']);
        self::assertSame('Thanks!', $normalized['replyText']);
        self::assertSame('locations/999', $normalized['sourceLocationId']);
        self::assertSame('Hotel London', $normalized['sourceLocationName']);
        self::assertSame('Google', $normalized['source']);
        self::assertTrue($normalized['isImported']);
    }

    public function testNormalizeReviewExtractsIdFromResourceName(): void
    {
        $normalized = $this->invokePrivate($this->service, 'normalizeReview', [
            'name' => 'accounts/1/locations/2/reviews/rev-99',
            'reviewer' => ['displayName' => 'Sam'],
            'starRating' => 4,
            'comment' => 'Nice',
        ]);

        self::assertSame('rev-99', $normalized['googleReviewId']);
        self::assertSame(4, $normalized['rating']);
    }

    public function testCalculateAverageRating(): void
    {
        $average = $this->invokePrivate($this->service, 'calculateAverageRating', [
            ['starRating' => 5],
            ['starRating' => 'THREE'],
            ['starRating' => 4],
        ]);

        self::assertSame(4.0, $average);
        self::assertNull($this->invokePrivate($this->service, 'calculateAverageRating', []));
    }

    public function testMockReviewsReturnDeterministicIds(): void
    {
        $reviews = $this->invokePrivate($this->service, 'mockReviews');

        self::assertCount(3, $reviews);
        self::assertSame('mock-1001', $reviews[0]['reviewId']);
        self::assertSame('mock-1002', $reviews[1]['reviewId']);
        self::assertSame('mock-1003', $reviews[2]['reviewId']);
    }
}
