<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Clock;

use App\Shared\Infrastructure\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testNowIsCloseToTheRealTimeAndInUtc(): void
    {
        $clock = new SystemClock();

        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = $clock->now();
        $after = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }
}
