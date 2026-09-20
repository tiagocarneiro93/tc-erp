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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * technical-scope.md §7.9.6: runs every vector in
 * tests/Fixtures/pricing-test-vectors.json against `PriceCalculator`
 * directly. The same vectors are the contract the web app and any
 * e-commerce integration must also satisfy (via the same fixture file, or
 * by calling `/calculate` with the vector's `input`).
 */
final class PriceCalculatorVectorsTest extends TestCase
{
    /**
     * The rates every vector needs, fixed here rather than reading real
     * seeded data: a vector's expected output must never silently change
     * because the seed data changed underneath it (docs/legal/civa-extracts.md
     * confirms these are the real mainland/Açores/Madeira rates as of
     * 2026-09-20, but this test pins them independently of that migration).
     */
    private const RATES = [
        'PT' => ['NOR' => '23.00', 'RED' => '6.00', 'INT' => '13.00', 'ISE' => '0.00'],
        'PT-AC' => ['NOR' => '16.00', 'RED' => '4.00', 'INT' => '9.00'],
        'PT-MA' => ['NOR' => '22.00', 'RED' => '5.00', 'INT' => '12.00'],
    ];

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function vectors(): iterable
    {
        $path = __DIR__.'/../../../Fixtures/pricing-test-vectors.json';
        /** @var array{vectors: list<array<string, mixed>>} $fixture */
        $fixture = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);

        foreach ($fixture['vectors'] as $vector) {
            /** @var string $name */
            $name = $vector['name'];
            yield $name => [$vector];
        }
    }

    /**
     * @param array<string, mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testVector(array $vector): void
    {
        /** @var array{pricing_mode: string, rounding_method: string, date: string, global_discount_percent: ?string, lines: list<array<string, mixed>>} $input */
        $input = $vector['input'];
        /** @var array{lines: list<array<string, mixed>>, tax_summary: list<array<string, mixed>>, net_total: string, tax_total: string, gross_total: string} $expected */
        $expected = $vector['expected'];

        $calculator = $this->calculator();

        $lines = array_map($this->buildLine(...), $input['lines']);
        $globalDiscountPercent = null !== $input['global_discount_percent']
            ? Percentage::fromString($input['global_discount_percent'])
            : null;

        $result = $calculator->calculate(
            PricingMode::from($input['pricing_mode']),
            RoundingMethod::from($input['rounding_method']),
            $lines,
            $globalDiscountPercent,
            new \DateTimeImmutable($input['date']),
        );

        self::assertSame($expected['net_total'], $result->netTotal()->toString(), 'net_total');
        self::assertSame($expected['tax_total'], $result->taxTotal()->toString(), 'tax_total');
        self::assertSame($expected['gross_total'], $result->grossTotal()->toString(), 'gross_total');

        self::assertCount(\count($expected['lines']), $result->lines(), 'line count');
        foreach ($expected['lines'] as $i => $expectedLine) {
            $actual = $result->lines()[$i];
            self::assertSame($expectedLine['line_amount_before_discounts'], $actual->lineAmountBeforeDiscounts()->toString(), "line $i line_amount_before_discounts");
            self::assertSame($expectedLine['discount_amount'], $actual->discountAmount()->toString(), "line $i discount_amount");
            self::assertSame($expectedLine['settlement_amount'], $actual->settlementAmount()->toString(), "line $i settlement_amount");
            self::assertSame($expectedLine['net_amount'], $actual->netAmount()->toString(), "line $i net_amount");
            self::assertSame($expectedLine['gross_amount'], $actual->grossAmount()->toString(), "line $i gross_amount");
            self::assertSame($expectedLine['tax_amount'], $actual->taxAmount()->toString(), "line $i tax_amount");
            self::assertSame($expectedLine['tax_region'], $actual->taxRegion(), "line $i tax_region");
            self::assertSame($expectedLine['tax_code'], $actual->taxCode(), "line $i tax_code");
            self::assertSame($expectedLine['tax_percentage'], $actual->taxPercentage()->toString(), "line $i tax_percentage");
            self::assertSame($expectedLine['exemption_reason_code'] ?? null, $actual->exemptionReasonCode(), "line $i exemption_reason_code");
        }

        self::assertCount(\count($expected['tax_summary']), $result->taxSummary(), 'tax_summary count');
        foreach ($expected['tax_summary'] as $i => $expectedEntry) {
            $actual = $result->taxSummary()[$i];
            self::assertSame($expectedEntry['tax_region'], $actual->taxRegion(), "tax_summary $i tax_region");
            self::assertSame($expectedEntry['tax_code'], $actual->taxCode(), "tax_summary $i tax_code");
            self::assertSame($expectedEntry['tax_percentage'], $actual->taxPercentage()->toString(), "tax_summary $i tax_percentage");
            self::assertSame($expectedEntry['taxable_base'], $actual->taxableBase()->toString(), "tax_summary $i taxable_base");
            self::assertSame($expectedEntry['tax_amount'], $actual->taxAmount()->toString(), "tax_summary $i tax_amount");
        }
    }

    /**
     * @param array<string, mixed> $line
     */
    private function buildLine(array $line): PriceCalculationLine
    {
        /** @var list<array{type: string, value: string}> $discountsRaw */
        $discountsRaw = $line['discounts'] ?? [];

        $discounts = [];
        foreach ($discountsRaw as $discount) {
            $discounts[] = 'percentage' === $discount['type']
                ? LineDiscount::percentage(Percentage::fromString($discount['value']))
                : LineDiscount::fixedAmount(Decimal::fromString($discount['value']));
        }

        /** @var string $quantity */
        $quantity = $line['quantity'];
        /** @var string $unitPrice */
        $unitPrice = $line['unit_price'];
        /** @var string $taxRegion */
        $taxRegion = $line['tax_region'];
        /** @var string $taxCode */
        $taxCode = $line['tax_code'];
        /** @var string|null $exemptionReasonCode */
        $exemptionReasonCode = $line['exemption_reason_code'] ?? null;

        return new PriceCalculationLine(
            Quantity::fromString($quantity),
            Decimal::fromString($unitPrice),
            $taxRegion,
            $taxCode,
            $discounts,
            $exemptionReasonCode,
        );
    }

    private function calculator(): PriceCalculator
    {
        $repository = $this->createMock(TaxRateRepository::class);
        $repository->method('findApplicable')->willReturnCallback(
            static function (string $region, string $code, \DateTimeImmutable $date): ?TaxRate {
                if (!isset(self::RATES[$region][$code])) {
                    return null;
                }

                return new TaxRate(TaxRateId::generate(), $region, $code, Percentage::fromString(self::RATES[$region][$code]), new \DateTimeImmutable('2011-01-01'), null, 'test');
            },
        );

        return new PriceCalculator(new TaxRateResolver($repository));
    }
}
