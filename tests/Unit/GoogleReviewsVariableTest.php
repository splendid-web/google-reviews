<?php

declare(strict_types=1);

namespace splendidweb\googlereviews\tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use splendidweb\googlereviews\tests\Support\InvokesPrivateMethods;
use splendidweb\googlereviews\variables\GoogleReviewsVariable;

final class GoogleReviewsVariableTest extends TestCase
{
    use InvokesPrivateMethods;

    #[DataProvider('summaryCandidatesProvider')]
    public function testLocationSummaryCandidates(string $locationId, array $expected): void
    {
        $variable = new GoogleReviewsVariable();

        self::assertSame(
            $expected,
            $this->invokePrivate($variable, 'locationSummaryCandidates', $locationId)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string[]}>
     */
    public static function summaryCandidatesProvider(): array
    {
        return [
            'empty' => ['', ['']],
            'bare id' => ['123456', ['123456', 'locations/123456', 'places/123456']],
            'locations resource' => ['locations/123456', ['locations/123456', '123456']],
            'places resource' => ['places/ChIJ123', ['places/ChIJ123', 'ChIJ123']],
        ];
    }
}
