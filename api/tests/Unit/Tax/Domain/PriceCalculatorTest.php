<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Percentage;
use App\Shared\Domain\Decimal\Quantity;
use App\Tax\Domain\LineDiscount;
use App\Tax\Domain\PriceCalculationLine;
use App\Tax\Domain\PriceCalculator;
use App\Tax\Domain\PricingMode;
use App\Tax\Domain\RoundingMethod;
use App\Tax\Domain\TaxRate;
use App\Tax\Domain\TaxRateId;
use App\Tax\Domain\TaxRateRepository;
use App\Tax\Domain\TaxRateResolver;
use PHPUnit\Framework\TestCase;

/**
 * docs/plans/phase-2.md task 2.1. The shared vectors in
 * pricing-test-vectors.json (exercised by {@see PriceCalculatorVectorsTest})
 * cover the worked examples from technical-scope.md §7.9 verbatim; this
 * suite covers behaviours the vectors don't (edge cases, discount
 * ordering, the largest-remainder algorithm's own invariants).
 */
final class PriceCalculatorTest extends TestCase
{
    private const DATE = '2026-01-01';

    public function testDiscountsAreAppliedCascadingInListOrder(): void
    {
        $calculator = $this->calculator(['NOR' => '23.00']);

        // 100 * 0.9 = 90, then 90 * 0.9 = 81 -- order matters, this is not
        // the same as a single combined 20% discount (which would be 80).
        $result = $calculator->calculate(
            PricingMode::Net,
            RoundingMethod::PerLine,
            [$this->line('1', '100.00', 'PT', 'NOR', [
                LineDiscount::percentage(Percentage::fromString('10.00')),
                LineDiscount::percentage(Percentage::fromString('10.00')),
            ])],
            null,
            $this->date(),
        );

        self::assertSame('81.00', $result->netTotal()->toString());
    }

    public function testFixedDiscountAppliedAfterPercentagesInListOrder(): void
    {
        $calculator = $this->calculator(['NOR' => '23.00']);

        $result = $calculator->calculate(
            PricingMode::Net,
            RoundingMethod::PerLine,
            [$this->line('1', '100.00', 'PT', 'NOR', [
                LineDiscount::fixedAmount(Decimal::fromString('50.00')),
                LineDiscount::percentage(Percentage::fromString('10.00')),
            ])],
            null,
            $this->date(),
        );

        // (100 - 50) * 0.9 = 45, not 100*0.9 - 50 = 40: the fixed amount
        // is subtracted first here because it's listed first.
        self::assertSame('45.00', $result->netTotal()->toString());
    }

    public function testNoDiscountsLeavesTheLineAmountUnchanged(): void
    {
        $calculator = $this->calculator(['NOR' => '23.00']);

        $result = $calculator->calculate(
            PricingMode::Net,
            RoundingMethod::PerLine,
            [$this->line('4', '25.50', 'PT', 'NOR')],
            null,
            $this->date(),
        );

        self::assertSame('102.00', $result->netTotal()->toString());
        self::assertSame('0.000000', $result->lines()[0]->discountAmount()->toString());
    }

    public function testGlobalDiscountAllocationsAlwaysSumToTheExactDiscountTotal(): void
    {
        $calculator = $this->calculator(['NOR' => '23.00']);

        // An amount deliberately not evenly divisible by 3 or by the
        // discount percentage, to exercise the largest-remainder path.
        $lines = [
            $this->line('1', '10.00', 'PT', 'NOR'),
            $this->line('1', '20.00', 'PT', 'NOR'),
            $this->line('1', '5.01', 'PT', 'NOR'),
        ];

        $result = $calculator->calculate(PricingMode::Net, RoundingMethod::PerLine, $lines, Percentage::fromString('7.00'), $this->date());

        $sumOfSettlements = array_reduce(
            $result->lines(),
            static fn (\Brick\Math\BigDecimal $carry, $line) => $carry->plus($line->settlementAmount()->toBigDecimal()),
            \Brick\Math\BigDecimal::zero(),
        );

        // 35.01 * 7% = 2.4507, rounded to 6 decimals -- the allocations
        // must sum to exactly this, never a unit adrift either way.
        self::assertSame('2.450700', $sumOfSettlements->toScale(6)->__toString());
    }

    public function testNoGlobalDiscountLeavesSettlementAmountsAtZero(): void
    {
        $calculator = $this->calculator(['NOR' => '23.00']);

        $result = $calculator->calculate(
            PricingMode::Net,
            RoundingMethod::PerLine,
            [$this->line('1', '100.00', 'PT', 'NOR')],
            null,
            $this->date(),
        );

        self::assertSame('0.000000', $result->lines()[0]->settlementAmount()->toString());
    }

    public function testZeroPercentGlobalDiscountBehavesLikeNoDiscount(): void
    {
        $calculator = $this->calculator(['NOR' => '23.00']);

        $result = $calculator->calculate(
            PricingMode::Net,
            RoundingMethod::PerLine,
            [$this->line('1', '100.00', 'PT', 'NOR')],
            Percentage::fromString('0.00'),
            $this->date(),
        );

        self::assertSame('0.000000', $result->lines()[0]->settlementAmount()->toString());
        self::assertSame('100.00', $result->netTotal()->toString());
    }

    public function testEmptyLinesProduceZeroTotalsAndNoTaxSummary(): void
    {
        $calculator = $this->calculator([]);

        $result = $calculator->calculate(PricingMode::Net, RoundingMethod::PerLine, [], null, $this->date());

        self::assertSame([], $result->lines());
        self::assertSame([], $result->taxSummary());
        self::assertSame('0.00', $result->netTotal()->toString());
    }

    public function testTaxSummaryHasOneEntryPerDistinctTaxKeyNotPerLine(): void
    {
        $calculator = $this->calculator(['NOR' => '23.00']);

        $result = $calculator->calculate(
            PricingMode::Net,
            RoundingMethod::PerLine,
            [
                $this->line('1', '10.00', 'PT', 'NOR'),
                $this->line('1', '20.00', 'PT', 'NOR'),
            ],
            null,
            $this->date(),
        );

        self::assertCount(1, $result->taxSummary());
        self::assertSame('30.00', $result->taxSummary()[0]->taxableBase()->toString());
    }

    public function testGrossModeReproducesTheExactGrossPriceTheCustomerSaw(): void
    {
        // technical-scope.md §7.9.1: the classic lost-cent case.
        $calculator = $this->calculator(['NOR' => '23.00']);

        $result = $calculator->calculate(
            PricingMode::Gross,
            RoundingMethod::PerLine,
            [$this->line('3', '9.99', 'PT', 'NOR')],
            null,
            $this->date(),
        );

        self::assertSame('29.97', $result->grossTotal()->toString());
    }

    public function testExemptionReasonCodeIsCarriedThroughUnvalidated(): void
    {
        $calculator = $this->calculator(['ISE' => '0.00']);

        $result = $calculator->calculate(
            PricingMode::Net,
            RoundingMethod::PerLine,
            [$this->line('1', '100.00', 'PT', 'ISE', [], 'M99')],
            null,
            $this->date(),
        );

        self::assertSame('M99', $result->lines()[0]->exemptionReasonCode());
    }

    /**
     * @param array<string, string> $percentagesByCode
     */
    private function calculator(array $percentagesByCode): PriceCalculator
    {
        $repository = $this->createMock(TaxRateRepository::class);
        $repository->method('findApplicable')->willReturnCallback(
            static function (string $region, string $code, \DateTimeImmutable $date) use ($percentagesByCode): ?TaxRate {
                if (!isset($percentagesByCode[$code])) {
                    return null;
                }

                return new TaxRate(TaxRateId::generate(), $region, $code, Percentage::fromString($percentagesByCode[$code]), new \DateTimeImmutable('2011-01-01'), null, 'test');
            },
        );

        return new PriceCalculator(new TaxRateResolver($repository));
    }

    /**
     * @param list<LineDiscount> $discounts
     */
    private function line(string $quantity, string $unitPrice, string $region, string $code, array $discounts = [], ?string $exemptionReasonCode = null): PriceCalculationLine
    {
        return new PriceCalculationLine(Quantity::fromString($quantity), Decimal::fromString($unitPrice), $region, $code, $discounts, $exemptionReasonCode);
    }

    private function date(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::DATE);
    }
}
