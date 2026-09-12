<?php

declare(strict_types=1);

namespace App\Shared\Domain\Tax;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets a module other
 * than Tax tell whether a `tax_rate_id` is the exempt (SAF-T `ISE`) rate,
 * without depending on `Tax\Domain\TaxRate` directly. Used to enforce that
 * an exemption reason is mandatory whenever the exempt rate is chosen
 * (technical-scope.md §6.5: `ISE` is one of the seeded SAF-T tax codes;
 * AT practice requires a motivo de isenção on every exempt line).
 */
interface TaxRateExemptionChecker
{
    /**
     * False for an unknown `$taxRateId` — existence is checked separately.
     */
    public function isExempt(string $taxRateId): bool;
}
