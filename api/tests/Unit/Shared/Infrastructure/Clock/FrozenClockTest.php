<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Clock;

use App\Shared\Infrastructure\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

final class FrozenClockTest extends TestCase
{
    public function testStaysAtTheGivenInstantUntilAdvanced(): void
    {
        $frozenAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $clock = new FrozenClock($frozenAt);

        self::assertEquals($frozenAt, $clock->now());
        self::assertEquals($frozenAt, $clock->now());

        $later = new \DateTimeImmutable('2026-06-15T12:00:00+00:00');
        $clock->setTo($later);

        self::assertEquals($later, $clock->now());
    }
}
