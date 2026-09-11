<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.2: a generic per-company key/value store ("default
 * warehouse, payment terms, PDF template, etc."). Empty for every company
 * as of task 1.4 — nothing yet has a default to seed, so this is schema and
 * repository only, no endpoint (docs/PLAN.md task 1.4 notes), consumed
 * starting with whichever later task needs its first key.
 *
 * @phpstan-type SettingValue array<string, mixed>
 */
final class CompanySetting
{
    /**
     * @param SettingValue $value
     */
    public function __construct(
        private readonly CompanyId $companyId,
        private readonly string $key,
        private array $value,
    ) {
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return SettingValue
     */
    public function value(): array
    {
        return $this->value;
    }

    /**
     * @param SettingValue $value
     */
    public function updateValue(array $value): void
    {
        $this->value = $value;
    }
}
