<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Percentage;

/**
 * One output line from {@see PriceCalculator}. `netAmount`/`grossAmount`/
 * `taxAmount` are stored at line precision (technical-scope.md §7.9.3: up
 * to 6 decimals) — under {@see RoundingMethod::PerLine} these are also the
 * document's authoritative 2-decimal figures for this line; under
 * {@see RoundingMethod::PerGroup} they're informational full-precision
 * values (SAF-T's own extra line-level precision), and the tax-key group
 * in {@see PriceCalculation::taxSummary()} carries the authoritative
 * rounded figures instead.
 */
final class CalculatedLine
{
    public function __construct(
        private readonly Decimal $lineAmountBeforeDiscounts,
        private readonly Decimal $discountAmount,
        private readonly Decimal $settlementAmount,
        private readonly Decimal $netAmount,
        private readonly Decimal $grossAmount,
        private readonly Decimal $taxAmount,
        private readonly string $taxRegion,
        private readonly string $taxCode,
        private readonly Percentage $taxPercentage,
        private readonly ?string $exemptionReasonCode,
    ) {
    }

    /**
     * `quantity × unit_price`, before any line or global discount.
     */
    public function lineAmountBeforeDiscounts(): Decimal
    {
        return $this->lineAmountBeforeDiscounts;
    }

    /**
     * The combined effect of this line's own discounts (not the global
     * discount, which is {@see settlementAmount()}).
     */
    public function discountAmount(): Decimal
    {
        return $this->discountAmount;
    }

    /**
     * This line's share of the document's global discount, per
     * technical-scope.md §7.9.4 step 3 (largest-remainder allocation) —
     * SAF-T's `SettlementAmount`.
     */
    public function settlementAmount(): Decimal
    {
        return $this->settlementAmount;
    }

    public function netAmount(): Decimal
    {
        return $this->netAmount;
    }

    public function grossAmount(): Decimal
    {
        return $this->grossAmount;
    }

    public function taxAmount(): Decimal
    {
        return $this->taxAmount;
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

    public function exemptionReasonCode(): ?string
    {
        return $this->exemptionReasonCode;
    }
}
