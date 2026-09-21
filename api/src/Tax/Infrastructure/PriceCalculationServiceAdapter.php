<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure;

use App\Shared\Domain\Tax\PriceCalculationService;
use App\Tax\Application\Query\CalculatePrice;
use App\Tax\Domain\CalculatedLine;
use App\Tax\Domain\Exception\InvalidCalculationLine;
use App\Tax\Domain\PriceCalculation;
use App\Tax\Domain\TaxSummaryEntry;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Implements the cross-module {@see PriceCalculationService} port by
 * dispatching the exact same {@see CalculatePrice} query
 * `Tax\UI\Http\CalculateController` uses — one calculation code path
 * (technical-scope.md §7.9.2 principle 1), reached here through the query
 * bus rather than a direct method call so this class needs no dependency
 * beyond `Tax.Application`'s own public message shape.
 */
final class PriceCalculationServiceAdapter implements PriceCalculationService
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    public function calculate(array $payload): ?array
    {
        $lines = $this->asListOfArrays($payload['lines'] ?? null);

        if (null === $lines) {
            return null;
        }

        $date = $payload['date'] ?? null;

        try {
            $parsedDate = \is_string($date) ? new \DateTimeImmutable($date) : new \DateTimeImmutable();
        } catch (\DateMalformedStringException) {
            // A malformed 'date' string — the draft is still being edited,
            // so this is "not calculable yet", not an error to report.
            return null;
        }

        try {
            /** @var PriceCalculation $result */
            $result = $this->handle(new CalculatePrice(
                \is_string($payload['pricing_mode'] ?? null) ? $payload['pricing_mode'] : 'net',
                \is_string($payload['rounding_method'] ?? null) ? $payload['rounding_method'] : 'per_line',
                $lines,
                \is_string($payload['global_discount_percent'] ?? null) ? $payload['global_discount_percent'] : null,
                $parsedDate,
            ));
        } catch (HandlerFailedException $e) {
            // HandleMessageMiddleware always wraps the handler's thrown
            // exception this way, sync bus or not. Bad line data, an
            // unknown pricing_mode/rounding_method, or an unresolvable tax
            // region/code all mean "not calculable yet" for a draft still
            // being edited — anything else is a real, unexpected failure.
            foreach ($e->getWrappedExceptions() as $wrapped) {
                if (!$wrapped instanceof InvalidCalculationLine && !$wrapped instanceof \ValueError && !$wrapped instanceof \InvalidArgumentException) {
                    throw $e;
                }
            }

            return null;
        }

        return [
            'lines' => array_map(self::lineToArray(...), $result->lines()),
            'tax_summary' => array_map(self::taxSummaryEntryToArray(...), $result->taxSummary()),
            'net_total' => $result->netTotal()->toString(),
            'tax_total' => $result->taxTotal()->toString(),
            'gross_total' => $result->grossTotal()->toString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function asListOfArrays(mixed $value): ?array
    {
        if (!\is_array($value) || [] === $value || !array_is_list($value)) {
            return null;
        }

        foreach ($value as $line) {
            if (!\is_array($line)) {
                return null;
            }
        }

        /** @var list<array<string, mixed>> $result */
        $result = $value;

        return $result;
    }

    /**
     * @return array{line_amount_before_discounts: string, discount_amount: string, settlement_amount: string, net_amount: string, gross_amount: string, tax_amount: string, tax_region: string, tax_code: string, tax_percentage: string, exemption_reason_code: ?string}
     */
    private static function lineToArray(CalculatedLine $line): array
    {
        return [
            'line_amount_before_discounts' => $line->lineAmountBeforeDiscounts()->toString(),
            'discount_amount' => $line->discountAmount()->toString(),
            'settlement_amount' => $line->settlementAmount()->toString(),
            'net_amount' => $line->netAmount()->toString(),
            'gross_amount' => $line->grossAmount()->toString(),
            'tax_amount' => $line->taxAmount()->toString(),
            'tax_region' => $line->taxRegion(),
            'tax_code' => $line->taxCode(),
            'tax_percentage' => $line->taxPercentage()->toString(),
            'exemption_reason_code' => $line->exemptionReasonCode(),
        ];
    }

    /**
     * @return array{tax_region: string, tax_code: string, tax_percentage: string, taxable_base: string, tax_amount: string}
     */
    private static function taxSummaryEntryToArray(TaxSummaryEntry $entry): array
    {
        return [
            'tax_region' => $entry->taxRegion(),
            'tax_code' => $entry->taxCode(),
            'tax_percentage' => $entry->taxPercentage()->toString(),
            'taxable_base' => $entry->taxableBase()->toString(),
            'tax_amount' => $entry->taxAmount()->toString(),
        ];
    }
}
