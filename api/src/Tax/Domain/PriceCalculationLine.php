<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Quantity;

/**
 * One input line to {@see PriceCalculator}. `unitPrice` is in the
 * calculation's {@see PricingMode} (net or gross) — the calculator never
 * mixes modes within one calculation.
 */
final class PriceCalculationLine
{
    /**
     * @param list<LineDiscount> $discounts applied in order, each to the
     *                                      running amount left by the previous one
     */
    public function __construct(
        private readonly Quantity $quantity,
        private readonly Decimal $unitPrice,
        private readonly string $taxRegion,
        private readonly string $taxCode,
        private readonly array $discounts = [],
        private readonly ?string $exemptionReasonCode = null,
    ) {
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function unitPrice(): Decimal
    {
        return $this->unitPrice;
    }

    public function taxRegion(): string
    {
        return $this->taxRegion;
    }

    public function taxCode(): string
    {
        return $this->taxCode;
    }

    /**
     * @return list<LineDiscount>
     */
    public function discounts(): array
    {
        return $this->discounts;
    }

    /**
     * Pass-through only — {@see PriceCalculator} doesn't validate that an
     * exempt line carries one; that's a draft/issuance validation concern
     * (docs/plans/phase-2.md task 2.4), not arithmetic.
     */
    public function exemptionReasonCode(): ?string
    {
        return $this->exemptionReasonCode;
    }
}
