<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Global reference data (technical-scope.md §6.4), seeded by a migration —
 * docs/plans/phase-1.md task 1.1 ships a starter set, not the full UN/CEFACT
 * unit list.
 */
final class Unit
{
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly int $decimals,
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function decimals(): int
    {
        return $this->decimals;
    }
}
