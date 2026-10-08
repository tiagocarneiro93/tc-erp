<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

/**
 * A series this run registered with AT, and where its numbering stands.
 */
final class RegisteredSeries
{
    private int $lastNumber = 0;

    public function __construct(
        public readonly string $documentType,
        public readonly string $code,
        public readonly string $validationCode,
    ) {
    }

    public function nextNumber(): int
    {
        return ++$this->lastNumber;
    }

    public function lastNumber(): int
    {
        return $this->lastNumber;
    }
}
