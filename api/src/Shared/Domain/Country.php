<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Global reference data (technical-scope.md §6.4 catalog / §6.3 parties both
 * use it), seeded by a migration. ISO 3166-1 alpha-2 codes — not legally
 * sensitive, no [VERIFY] (docs/plans/phase-1.md task 1.1).
 */
final class Country
{
    public function __construct(
        private readonly string $code,
        private readonly string $name,
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
}
