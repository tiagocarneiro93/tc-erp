<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Percentage;
use App\Shared\Domain\Decimal\Quantity;
use App\Tax\Domain\Exception\InvalidCalculationLine;
use App\Tax\Domain\Exception\NoApplicableTaxRate;
use App\Tax\Domain\LineDiscount;
use App\Tax\Domain\PriceCalculation;
use App\Tax\Domain\PriceCalculationLine;
use App\Tax\Domain\PriceCalculator;
use App\Tax\Domain\PricingMode;
use App\Tax\Domain\RoundingMethod;
use Brick\Math\Exception\MathException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `/calculate` is deliberately public reference-data-shaped: no company
 * scope is read, no permission check — it's a pure function of its input,
 * the same one `PriceCalculator` runs at issuance (technical-scope.md
 * §7.9.2 principle 1). A tax region/code the caller made up is *this*
 * endpoint's fault to report as a 422 (unlike issuance, where the region/
 * code comes from already-validated product/company data and a resolution
 * failure is genuinely unexpected) — so {@see NoApplicableTaxRate} is
 * remapped to {@see InvalidCalculationLine} here, and only here.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class CalculatePriceHandler
{
    public function __construct(
        private readonly PriceCalculator $calculator,
    ) {
    }

    public function __invoke(CalculatePrice $query): PriceCalculation
    {
        $mode = PricingMode::from($query->pricingMode);
        $roundingMethod = RoundingMethod::from($query->roundingMethod);
        $globalDiscountPercent = null !== $query->globalDiscountPercent
            ? $this->parseDecimal(Percentage::class, $query->globalDiscountPercent, null, 'global_discount_percent')
            : null;

        $lines = [];
        foreach ($query->lines as $index => $line) {
            $lines[] = $this->buildLine($index, $line);
        }

        try {
            return $this->calculator->calculate($mode, $roundingMethod, $lines, $globalDiscountPercent, $query->date);
        } catch (NoApplicableTaxRate $e) {
            throw new InvalidCalculationLine(null, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $line
     */
    private function buildLine(int $index, array $line): PriceCalculationLine
    {
        $quantity = $this->parseDecimal(Quantity::class, $this->requireString($line, 'quantity', $index), $index, 'quantity');
        $unitPrice = $this->parseDecimal(Decimal::class, $this->requireString($line, 'unit_price', $index), $index, 'unit_price');
        $taxRegion = $this->requireString($line, 'tax_region', $index);
        $taxCode = $this->requireString($line, 'tax_code', $index);
        $exemptionReasonCode = \is_string($line['exemption_reason_code'] ?? null) ? $line['exemption_reason_code'] : null;

        $discounts = [];
        /** @var mixed $discount */
        foreach ((array) ($line['discounts'] ?? []) as $discount) {
            if (!\is_array($discount)) {
                throw new InvalidCalculationLine($index, 'Each discount must be an object with "type" and "value".');
            }

            $discounts[] = $this->buildDiscount($index, $discount);
        }

        return new PriceCalculationLine($quantity, $unitPrice, $taxRegion, $taxCode, $discounts, $exemptionReasonCode);
    }

    /**
     * @param array<mixed, mixed> $discount
     */
    private function buildDiscount(int $index, array $discount): LineDiscount
    {
        $value = $this->requireString($discount, 'value', $index);

        return match ($discount['type'] ?? null) {
            'percentage' => LineDiscount::percentage($this->parseDecimal(Percentage::class, $value, $index, 'discounts.value')),
            'fixed_amount' => LineDiscount::fixedAmount($this->parseDecimal(Decimal::class, $value, $index, 'discounts.value')),
            default => throw new InvalidCalculationLine($index, 'Each discount needs a "type" of "percentage" or "fixed_amount".'),
        };
    }

    /**
     * @param array<mixed, mixed> $data
     */
    private function requireString(array $data, string $field, int $lineIndex): string
    {
        $value = $data[$field] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw new InvalidCalculationLine($lineIndex, \sprintf('"%s" is required.', $field));
        }

        return $value;
    }

    /**
     * @template T of Decimal
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function parseDecimal(string $class, string $value, ?int $lineIndex, string $field): Decimal
    {
        try {
            return $class::fromString($value);
        } catch (MathException) {
            throw new InvalidCalculationLine($lineIndex, \sprintf('"%s" is not a valid decimal: "%s".', $field, $value));
        }
    }
}
