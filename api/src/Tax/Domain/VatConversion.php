<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Percentage;
use Brick\Math\RoundingMode;

/**
 * technical-scope.md §7.9.8: converting a price entered in one VAT mode
 * (gross/net) into the other, at 6 decimals — "Conversion into a document
 * of the other mode ... the unit price is converted at 6 decimals
 * (9,99 / 1,23 = 8,121951)". Rounding mode HALF_UP per §14 decision 7
 * (itself marked **[VERIFY]** against AT guidance in the scope — reused
 * here as-is, not re-decided by this task).
 *
 * docs/plans/phase-1.md decision 2: this is the first building block of
 * the real `PriceCalculator` (Phase 2) — a small pure function, no
 * discounts, no multi-line totals, so Phase 2 extends it rather than
 * replacing it (§7.9.2 principle 1: one calculator, no second
 * implementation).
 */
final class VatConversion
{
    private const SCALE = 6;

    public static function toNet(Decimal $grossAmount, Percentage $rate): Decimal
    {
        $divisor = $rate->asMultiplier()->plus(1);

        return Decimal::fromBigDecimal($grossAmount->toBigDecimal()->dividedBy($divisor, self::SCALE, RoundingMode::HalfUp));
    }

    public static function toGross(Decimal $netAmount, Percentage $rate): Decimal
    {
        $multiplier = $rate->asMultiplier()->plus(1);

        return Decimal::fromBigDecimal($netAmount->toBigDecimal()->multipliedBy($multiplier)->toScale(self::SCALE, RoundingMode::HalfUp));
    }
}
