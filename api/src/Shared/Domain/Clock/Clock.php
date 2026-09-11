<?php

declare(strict_types=1);

namespace App\Shared\Domain\Clock;

/**
 * Domain and Application code must obtain the current time through this
 * port, never `new DateTimeImmutable()` directly (CLAUDE.md — architecture
 * rules), so tests can freeze time and chronology logic stays deterministic.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
