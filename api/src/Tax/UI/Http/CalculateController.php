<?php

declare(strict_types=1);

namespace App\Tax\UI\Http;

use App\Shared\Domain\Clock\Clock;
use App\Tax\Application\Query\CalculatePrice;
use App\Tax\Domain\CalculatedLine;
use App\Tax\Domain\PriceCalculation;
use App\Tax\Domain\TaxSummaryEntry;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * technical-scope.md §7.9.2/§7.9.6: the one place that turns raw lines
 * into a canonical calculation — the UI, e-commerce integrations, and
 * (from docs/plans/phase-2.md task 2.4 onward) drafts and issuance all
 * produce the same output for the same input. Display-only here: nothing
 * this endpoint receives is ever persisted. `{companyId}` is resolved and
 * authorized (membership) by `CompanyRouteListener`; no further permission
 * check — any authenticated member may preview a calculation, same as the
 * global reference-data endpoints.
 */
#[OA\Tag(name: 'Tax')]
final class CalculateController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
        private readonly Clock $clock,
    ) {
        $this->messageBus = $queryBus;
    }

    #[Route('/api/v1/companies/{companyId}/calculate', name: 'tax_calculate', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The canonical calculation for these lines (never persisted).', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'lines', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'line_amount_before_discounts', type: 'string'),
            new OA\Property(property: 'discount_amount', type: 'string'),
            new OA\Property(property: 'settlement_amount', type: 'string'),
            new OA\Property(property: 'net_amount', type: 'string'),
            new OA\Property(property: 'gross_amount', type: 'string'),
            new OA\Property(property: 'tax_amount', type: 'string'),
            new OA\Property(property: 'tax_region', type: 'string'),
            new OA\Property(property: 'tax_code', type: 'string'),
            new OA\Property(property: 'tax_percentage', type: 'string'),
            new OA\Property(property: 'exemption_reason_code', type: 'string', nullable: true),
        ], type: 'object')),
        new OA\Property(property: 'tax_summary', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'tax_region', type: 'string'),
            new OA\Property(property: 'tax_code', type: 'string'),
            new OA\Property(property: 'tax_percentage', type: 'string'),
            new OA\Property(property: 'taxable_base', type: 'string'),
            new OA\Property(property: 'tax_amount', type: 'string'),
        ], type: 'object')),
        new OA\Property(property: 'net_total', type: 'string'),
        new OA\Property(property: 'tax_total', type: 'string'),
        new OA\Property(property: 'gross_total', type: 'string'),
    ]))]
    #[OA\Response(response: 422, description: 'A line is invalid (bad decimal, unknown discount type, or unresolvable tax region/code), or the request payload failed validation.')]
    public function calculate(#[MapRequestPayload] CalculateRequest $request): JsonResponse
    {
        /** @var PriceCalculation $result */
        $result = $this->handle(new CalculatePrice(
            $request->pricing_mode,
            $request->rounding_method,
            $request->lines,
            $request->global_discount_percent,
            $this->parseDate($request->date),
        ));

        return new JsonResponse([
            'lines' => array_map(self::lineToArray(...), $result->lines()),
            'tax_summary' => array_map(self::taxSummaryEntryToArray(...), $result->taxSummary()),
            'net_total' => $result->netTotal()->toString(),
            'tax_total' => $result->taxTotal()->toString(),
            'gross_total' => $result->grossTotal()->toString(),
        ]);
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

    private function parseDate(?string $date): \DateTimeImmutable
    {
        if (null === $date) {
            return $this->clock->now();
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (false === $parsed) {
            throw new BadRequestHttpException('Invalid date, expected YYYY-MM-DD.');
        }

        return $parsed;
    }
}
