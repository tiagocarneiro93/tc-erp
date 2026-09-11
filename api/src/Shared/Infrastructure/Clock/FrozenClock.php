<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Clock;

use App\Shared\Domain\Clock\Clock;

/**
 * Test double: holds a fixed instant, advanceable with {@see self::setTo()}.
 */
final class FrozenClock implements Clock
{
    private \DateTimeImmutable $now;

    public function __construct(\DateTimeImmutable $now)
    {
        $this->now = $now;
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function setTo(\DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
