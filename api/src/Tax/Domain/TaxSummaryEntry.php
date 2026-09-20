<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Decimal\Percentage;

/**
 * One tax key's rolled-up totals — SAF-T `document_tax_summary`
 * (technical-scope.md §6.6). Always the authoritative 2-decimal figures
 * for this `(taxRegion, taxCode, taxPercentage)` key, regardless of
 * {@see RoundingMethod}: under `PerLine` it's the sum of already-rounded
 * line values; under `PerGroup` it's rounded once here, from the group's
 * full-precision total.
 */
final class TaxSummaryEntry
{
    public function __construct(
        private readonly string $taxRegion,
        private readonly string $taxCode,
        private readonly Percentage $taxPercentage,
        private readonly Money $taxableBase,
        private readonly Money $taxAmount,
    ) {
    }

    public function taxRegion(): string
    {
        return $this->taxRegion;
    }

    public function taxCode(): string
    {
        return $this->taxCode;
    }

    public function taxPercentage(): Percentage
    {
        return $this->taxPercentage;
    }

    public function taxableBase(): Money
    {
        return $this->taxableBase;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    /**
     * Stable grouping key for `(taxRegion, taxCode)` — percentage is a
     * function of the two plus the calculation date, so it never
     * disambiguates lines the code/region already put in the same group.
     */
    public static function key(string $taxRegion, string $taxCode): string
    {
        return $taxRegion.'|'.$taxCode;
    }
}
