<?php

declare(strict_types=1);

namespace splendidweb\googlereviews\tests\Unit;

use PHPUnit\Framework\TestCase;
use splendidweb\googlereviews\models\SyncResult;

final class SyncResultTest extends TestCase
{
    public function testDefaultsHaveNoErrors(): void
    {
        $result = new SyncResult();

        self::assertFalse($result->hasSyncErrors());
        self::assertSame(0, $result->fetched);
        self::assertSame(0, $result->upserted);
        self::assertSame(0, $result->skipped);
        self::assertSame(0, $result->archived);
        self::assertSame([], $result->errors);
    }

    public function testHasSyncErrorsWhenMessagesPresent(): void
    {
        $result = new SyncResult();
        $result->errors[] = 'Something failed';

        self::assertTrue($result->hasSyncErrors());
    }
}
