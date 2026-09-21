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
        private readonly Money $settlementTotal,
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

    /**
     * `documents.settlement_total` (technical-scope.md §6.6): the sum of
     * every line's `settlementAmount()` — the total discount allocated
     * from the document-level global discount (§7.9.4 step 3), rounded to
     * money scale the same way `netTotal`/`taxTotal`/`grossTotal` are.
     * Zero when there is no global discount.
     */
    public function settlementTotal(): Money
    {
        return $this->settlementTotal;
    }
}
