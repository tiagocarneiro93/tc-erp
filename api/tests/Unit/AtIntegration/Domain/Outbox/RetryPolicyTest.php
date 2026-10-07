<?php

declare(strict_types=1);

namespace App\Tests\Unit\AtIntegration\Domain\Outbox;

use App\AtIntegration\Domain\Outbox\RetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function delays(): iterable
    {
        yield 'after the 1st attempt' => [1, 2];
        yield 'after the 2nd attempt' => [2, 4];
        yield 'after the 3rd attempt' => [3, 8];
        yield 'after the 8th attempt' => [8, 256];
        yield 'capped at six hours' => [9, 360];
        yield 'still capped' => [20, 360];
    }

    #[DataProvider('delays')]
    public function testTheBackoffDoublesUpToACap(int $attemptsMade, int $expectedMinutes): void
    {
        $now = new \DateTimeImmutable('2026-03-05T10:00:00+00:00');

        $next = RetryPolicy::nextAttemptAt($now, $attemptsMade);

        self::assertSame($expectedMinutes * 60, $next->getTimestamp() - $now->getTimestamp());
    }

    public function testTheLeaseIsTenMinutes(): void
    {
        $now = new \DateTimeImmutable('2026-03-05T10:00:00+00:00');

        self::assertSame(600, RetryPolicy::leaseUntil($now)->getTimestamp() - $now->getTimestamp());
    }
}
