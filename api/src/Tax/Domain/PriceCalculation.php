<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Money;

/**
 * The result of {@see PriceCalculator::calculate()} — one document's
 * canonical lines, tax summary and totals (technical-scope.md §7.9.4 step
 * 6). `netTotal`/`taxTotal`/`grossTotal` are always the sum of
 * {@see taxSummary()}'s entries, never a separate recomputation.
 */
final class PriceCalculation
{
    /**
     * @param list<CalculatedLine>  $lines
     * @param list<TaxSummaryEntry> $taxSummary
     */
    public function __construct(
        private readonly array $lines,
        private readonly array $taxSummary,
        private readonly Money $netTotal,
        private readonly Money $taxTotal,
        private readonly Money $grossTotal,
    ) {
    }

    /**
     * @return list<CalculatedLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<TaxSummaryEntry>
     */
    public function taxSummary(): array
    {
        return $this->taxSummary;
    }

    public function netTotal(): Money
    {
        return $this->netTotal;
    }

    public function taxTotal(): Money
    {
        return $this->taxTotal;
    }

    public function grossTotal(): Money
    {
        return $this->grossTotal;
    }
}
