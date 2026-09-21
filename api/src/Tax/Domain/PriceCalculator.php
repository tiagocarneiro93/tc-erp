<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Decimal\Percentage;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

/**
 * technical-scope.md §7.9.2 principle 1: the only code that computes
 * lines, discounts, VAT and totals. The UI, the API, e-commerce
 * integrations and issuance all go through this (via `/calculate` or at
 * issuance) — no other implementation exists.
 *
 * Extends Phase 1's `VatConversion` (docs/plans/phase-1.md decision 2) in
 * spirit, not by inheritance: `VatConversion` stays as the narrow
 * single-price net/gross toggle Catalog's product-price preview uses
 * (§7.9.8); this class is the full per-document algorithm (§7.9.4/7.9.5),
 * built on the same `TaxRateResolver` and HALF_UP rounding.
 *
 * Order of operations is fixed (§7.9.4): line amount -> line discount(s)
 * -> global discount (allocated by largest remainder) -> VAT last.
 */
final class PriceCalculator
{
    private const LINE_SCALE = 6;
    private const MONEY_SCALE = 2;

    public function __construct(
        private readonly TaxRateResolver $taxRates,
    ) {
    }

    /**
     * @param list<PriceCalculationLine> $lines
     */
    public function calculate(
        PricingMode $mode,
        RoundingMethod $roundingMethod,
        array $lines,
        ?Percentage $globalDiscountPercent,
        \DateTimeImmutable $date,
    ): PriceCalculation {
        $lineAmountsBeforeDiscounts = [];
        $afterLineDiscounts = [];

        foreach ($lines as $i => $line) {
            $before = $line->quantity()->toBigDecimal()->multipliedBy($line->unitPrice()->toBigDecimal());
            $lineAmountsBeforeDiscounts[$i] = $before;
            $afterLineDiscounts[$i] = $this->applyLineDiscounts($before, $line->discounts());
        }

        $totalAfterLineDiscounts = array_reduce(
            $afterLineDiscounts,
            static fn (BigDecimal $carry, BigDecimal $amount): BigDecimal => $carry->plus($amount),
            BigDecimal::zero(),
        );

        $settlementAmounts = null !== $globalDiscountPercent && !$globalDiscountPercent->isZero()
            ? $this->allocateGlobalDiscount($afterLineDiscounts, $totalAfterLineDiscounts, $globalDiscountPercent)
            : array_fill_keys(array_keys($afterLineDiscounts), BigDecimal::zero()->toScale(self::LINE_SCALE));

        /** @var array<string, TaxRate> $rateCache */
        $rateCache = [];
        $resolveRate = function (string $region, string $code) use (&$rateCache, $date): TaxRate {
            $key = TaxSummaryEntry::key($region, $code);

            return $rateCache[$key] ??= $this->taxRates->resolve($region, $code, $date);
        };

        $taxableBases = [];
        foreach ($lines as $i => $line) {
            $taxableBases[$i] = $afterLineDiscounts[$i]->minus($settlementAmounts[$i]);
        }

        return RoundingMethod::PerLine === $roundingMethod
            ? $this->calculatePerLine($mode, $lines, $lineAmountsBeforeDiscounts, $afterLineDiscounts, $settlementAmounts, $taxableBases, $resolveRate)
            : $this->calculatePerGroup($mode, $lines, $lineAmountsBeforeDiscounts, $afterLineDiscounts, $settlementAmounts, $taxableBases, $resolveRate);
    }

    /**
     * Line amounts are stored with up to 6 decimals (technical-scope.md
     * §7.9.3) — raw discount/multiplication arithmetic can carry far more
     * scale than that, which is meaningless for storage or display.
     */
    private function round6(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale(self::LINE_SCALE, RoundingMode::HalfUp);
    }

    /**
     * @param list<LineDiscount> $discounts
     */
    private function applyLineDiscounts(BigDecimal $amount, array $discounts): BigDecimal
    {
        foreach ($discounts as $discount) {
            $amount = $discount->isPercentage()
                ? $amount->multipliedBy(BigDecimal::one()->minus($discount->percentageValue()->asMultiplier()))
                : $amount->minus($discount->fixedAmountValue()->toBigDecimal());
        }

        return $amount;
    }

    /**
     * technical-scope.md §7.9.4 step 3: allocates the global discount
     * across lines proportionally, using the largest-remainder method so
     * the allocations sum exactly to the (line-precision-rounded) discount
     * total — never a cent adrift from rounding each line's share
     * independently.
     *
     * Deliberately computes each line's *exact* share as `amount_i × rate`
     * directly, never via `amount_i ÷ total × discountTotal`. The two are
     * not the same computation: dividing by `total` is inherently an
     * approximation (rounded to a finite, if generous, working scale),
     * whereas `Σ(amount_i × rate) = total × rate` exactly, by
     * distributivity — no division needed. An earlier revision used the
     * division form; empirically (see the git history for this file) it
     * produced byte-identical output to this one on every case tried,
     * including adversarial ones combining line-level and global
     * discounts, because the division's working scale (`LINE_SCALE + 14`)
     * left far more headroom than any realistic invoice amount could
     * exhaust. So this is not a fix for an observed bug — it's kept
     * because it is the exact computation with one fewer operation, not
     * because the division form was shown to misbehave in practice. The
     * only genuine rounding to redistribute is the (small, bounded) gap
     * between the aggregate `discountTotal` — itself rounded to line
     * precision — and the exact per-line shares.
     *
     * @param array<int, BigDecimal> $afterLineDiscounts
     *
     * @return array<int, BigDecimal> settlement amount per line index, scale 6
     */
    private function allocateGlobalDiscount(array $afterLineDiscounts, BigDecimal $total, Percentage $globalDiscountPercent): array
    {
        if ($total->isZero()) {
            return array_fill_keys(array_keys($afterLineDiscounts), BigDecimal::zero()->toScale(self::LINE_SCALE));
        }

        $rateMultiplier = $globalDiscountPercent->asMultiplier();
        $discountTotal = $total->multipliedBy($rateMultiplier)->toScale(self::LINE_SCALE, RoundingMode::HalfUp);
        $targetUnits = $discountTotal->withPointMovedRight(self::LINE_SCALE)->toBigInteger();

        $flooredUnits = [];
        $remainders = [];
        $sumFloored = BigInteger::zero();

        foreach ($afterLineDiscounts as $i => $amount) {
            $exactShareUnits = $amount->multipliedBy($rateMultiplier)->withPointMovedRight(self::LINE_SCALE);
            $floored = $exactShareUnits->toScale(0, RoundingMode::Down)->toBigInteger();
            $flooredUnits[$i] = $floored;
            $remainders[$i] = $exactShareUnits->minus($floored);
            $sumFloored = $sumFloored->plus($floored);
        }

        $leftover = $targetUnits->minus($sumFloored)->toInt();

        $order = array_keys($afterLineDiscounts);
        usort($order, static function (int $a, int $b) use ($remainders): int {
            $cmp = $remainders[$b]->compareTo($remainders[$a]);

            return 0 !== $cmp ? $cmp : $a <=> $b;
        });

        for ($rank = 0; $rank < $leftover && $rank < \count($order); ++$rank) {
            $flooredUnits[$order[$rank]] = $flooredUnits[$order[$rank]]->plus(1);
        }

        $settlements = [];
        foreach ($flooredUnits as $i => $units) {
            $settlements[$i] = BigDecimal::of($units)->withPointMovedLeft(self::LINE_SCALE);
        }

        return $settlements;
    }

    /**
     * @param list<PriceCalculationLine>        $lines
     * @param array<int, BigDecimal>            $lineAmountsBeforeDiscounts
     * @param array<int, BigDecimal>            $afterLineDiscounts
     * @param array<int, BigDecimal>            $settlementAmounts
     * @param array<int, BigDecimal>            $taxableBases
     * @param callable(string, string): TaxRate $resolveRate
     */
    private function calculatePerLine(
        PricingMode $mode,
        array $lines,
        array $lineAmountsBeforeDiscounts,
        array $afterLineDiscounts,
        array $settlementAmounts,
        array $taxableBases,
        callable $resolveRate,
    ): PriceCalculation {
        $calculatedLines = [];
        /** @var array<string, array{region: string, code: string, rate: TaxRate, base: BigDecimal, vat: BigDecimal}> $groups */
        $groups = [];

        foreach ($lines as $i => $line) {
            $rate = $resolveRate($line->taxRegion(), $line->taxCode());
            $base = $taxableBases[$i];

            if (PricingMode::Net === $mode) {
                $net = $base->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
                $vat = $net->multipliedBy($rate->percentage()->asMultiplier())->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
                $gross = $net->plus($vat);
            } else {
                $gross = $base->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
                $net = $gross->dividedBy(BigDecimal::one()->plus($rate->percentage()->asMultiplier()), self::MONEY_SCALE, RoundingMode::HalfUp);
                $vat = $gross->minus($net);
            }

            $key = TaxSummaryEntry::key($line->taxRegion(), $line->taxCode());
            $groups[$key] ??= ['region' => $line->taxRegion(), 'code' => $line->taxCode(), 'rate' => $rate, 'base' => BigDecimal::zero(), 'vat' => BigDecimal::zero()];
            $groups[$key]['base'] = $groups[$key]['base']->plus($net);
            $groups[$key]['vat'] = $groups[$key]['vat']->plus($vat);

            $calculatedLines[] = new CalculatedLine(
                Decimal::fromBigDecimal($this->round6($lineAmountsBeforeDiscounts[$i])),
                Decimal::fromBigDecimal($this->round6($lineAmountsBeforeDiscounts[$i]->minus($afterLineDiscounts[$i]))),
                Decimal::fromBigDecimal($settlementAmounts[$i]),
                Decimal::fromBigDecimal($net),
                Decimal::fromBigDecimal($gross),
                Decimal::fromBigDecimal($vat),
                $line->taxRegion(),
                $line->taxCode(),
                $rate->percentage(),
                $line->exemptionReasonCode(),
            );
        }

        return $this->buildResult($calculatedLines, $groups);
    }

    /**
     * @param list<PriceCalculationLine>        $lines
     * @param array<int, BigDecimal>            $lineAmountsBeforeDiscounts
     * @param array<int, BigDecimal>            $afterLineDiscounts
     * @param array<int, BigDecimal>            $settlementAmounts
     * @param array<int, BigDecimal>            $taxableBases
     * @param callable(string, string): TaxRate $resolveRate
     */
    private function calculatePerGroup(
        PricingMode $mode,
        array $lines,
        array $lineAmountsBeforeDiscounts,
        array $afterLineDiscounts,
        array $settlementAmounts,
        array $taxableBases,
        callable $resolveRate,
    ): PriceCalculation {
        /** @var array<string, array{region: string, code: string, rate: TaxRate, raw: BigDecimal}> $groups */
        $groups = [];

        foreach ($lines as $i => $line) {
            $key = TaxSummaryEntry::key($line->taxRegion(), $line->taxCode());
            $groups[$key] ??= ['region' => $line->taxRegion(), 'code' => $line->taxCode(), 'rate' => $resolveRate($line->taxRegion(), $line->taxCode()), 'raw' => BigDecimal::zero()];
            $groups[$key]['raw'] = $groups[$key]['raw']->plus($taxableBases[$i]);
        }

        $groupTotals = [];
        foreach ($groups as $key => $group) {
            $rate = $group['rate'];

            if (PricingMode::Net === $mode) {
                $base = $group['raw']->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
                $vat = $base->multipliedBy($rate->percentage()->asMultiplier())->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
            } else {
                $gross = $group['raw']->toScale(self::MONEY_SCALE, RoundingMode::HalfUp);
                $base = $gross->dividedBy(BigDecimal::one()->plus($rate->percentage()->asMultiplier()), self::MONEY_SCALE, RoundingMode::HalfUp);
                $vat = $gross->minus($base);
            }

            $groupTotals[$key] = ['region' => $group['region'], 'code' => $group['code'], 'rate' => $rate, 'base' => $base, 'vat' => $vat];
        }

        $calculatedLines = [];
        foreach ($lines as $i => $line) {
            $key = TaxSummaryEntry::key($line->taxRegion(), $line->taxCode());
            $rate = $groups[$key]['rate'];
            $base = $taxableBases[$i];

            if (PricingMode::Net === $mode) {
                $net = $base->toScale(self::LINE_SCALE, RoundingMode::HalfUp);
                $vat = $base->multipliedBy($rate->percentage()->asMultiplier())->toScale(self::LINE_SCALE, RoundingMode::HalfUp);
                $gross = $net->plus($vat);
            } else {
                $gross = $base->toScale(self::LINE_SCALE, RoundingMode::HalfUp);
                $net = $base->dividedBy(BigDecimal::one()->plus($rate->percentage()->asMultiplier()), self::LINE_SCALE, RoundingMode::HalfUp);
                $vat = $gross->minus($net);
            }

            $calculatedLines[] = new CalculatedLine(
                Decimal::fromBigDecimal($this->round6($lineAmountsBeforeDiscounts[$i])),
                Decimal::fromBigDecimal($this->round6($lineAmountsBeforeDiscounts[$i]->minus($afterLineDiscounts[$i]))),
                Decimal::fromBigDecimal($settlementAmounts[$i]),
                Decimal::fromBigDecimal($net),
                Decimal::fromBigDecimal($gross),
                Decimal::fromBigDecimal($vat),
                $line->taxRegion(),
                $line->taxCode(),
                $rate->percentage(),
                $line->exemptionReasonCode(),
            );
        }

        return $this->buildResult($calculatedLines, $groupTotals);
    }

    /**
     * @param list<CalculatedLine>                                                                                 $calculatedLines
     * @param array<string, array{region: string, code: string, rate: TaxRate, base: BigDecimal, vat: BigDecimal}> $groups
     */
    private function buildResult(array $calculatedLines, array $groups): PriceCalculation
    {
        $taxSummary = [];
        $netTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();

        foreach ($groups as $group) {
            $taxSummary[] = new TaxSummaryEntry(
                $group['region'],
                $group['code'],
                $group['rate']->percentage(),
                Money::fromString($group['base']->__toString()),
                Money::fromString($group['vat']->__toString()),
            );
            $netTotal = $netTotal->plus($group['base']);
            $taxTotal = $taxTotal->plus($group['vat']);
        }

        $settlementTotal = BigDecimal::zero();
        foreach ($calculatedLines as $calculatedLine) {
            $settlementTotal = $settlementTotal->plus($calculatedLine->settlementAmount()->toBigDecimal());
        }

        return new PriceCalculation(
            $calculatedLines,
            $taxSummary,
            Money::fromString($netTotal->__toString()),
            Money::fromString($taxTotal->__toString()),
            Money::fromString($netTotal->plus($taxTotal)->__toString()),
            Money::fromString($settlementTotal->toScale(self::MONEY_SCALE, RoundingMode::HalfUp)->__toString()),
        );
    }
}
