<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\AtIntegration\AtCommunicableLine;
use App\Shared\Domain\AtIntegration\AtCommunicableTaxBucket;
use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Percentage;
use App\Shared\Domain\Decimal\Quantity;
use App\Tax\Domain\LineDiscount;
use App\Tax\Domain\PriceCalculationLine;
use App\Tax\Domain\PriceCalculator;
use App\Tax\Domain\PricingMode;
use App\Tax\Domain\RoundingMethod;
use App\Tax\Domain\TaxRateResolver;
use Brick\Math\BigDecimal;

/**
 * Turns a {@see DocumentScenario} into the stored-data shape the e-Fatura
 * request builder consumes ({@see AtCommunicableDocument}). Every amount comes
 * from the real `PriceCalculator`; this class only maps its output.
 */
final class ScenarioDocumentFactory
{
    private const SECTIONS = [
        'FT' => 'SalesInvoices', 'FS' => 'SalesInvoices', 'FR' => 'SalesInvoices', 'NC' => 'SalesInvoices', 'ND' => 'SalesInvoices',
        'OR' => 'WorkingDocuments', 'PF' => 'WorkingDocuments', 'NE' => 'WorkingDocuments',
    ];

    private readonly PriceCalculator $calculator;

    public function __construct(private readonly string $issuerNif)
    {
        $this->calculator = new PriceCalculator(new TaxRateResolver(new FixedTaxRates()));
    }

    /**
     * @param list<string> $referencedDocumentNos the numbers of the documents this one references, already resolved
     */
    public function build(DocumentScenario $scenario, RegisteredSeries $series, array $referencedDocumentNos, \DateTimeImmutable $now): AtCommunicableDocument
    {
        $section = self::SECTIONS[$scenario->documentType] ?? throw new \InvalidArgumentException(\sprintf('Scenario "%s": "%s" is not a document type the live suite sends.', $scenario->name, $scenario->documentType));
        $issueDate = new \DateTimeImmutable($scenario->issueDate ?? $now->format('Y-m-d'), new \DateTimeZone('UTC'));
        $number = $series->nextNumber();

        $calculation = $this->calculator->calculate(
            PricingMode::from($scenario->pricingMode),
            RoundingMethod::from($scenario->roundingMethod),
            array_map(static fn (array $line): PriceCalculationLine => new PriceCalculationLine(
                Quantity::fromString($line['quantity']),
                Decimal::fromString($line['unit_price']),
                $line['tax_region'],
                $line['tax_code'],
                null === $line['discount_percent'] ? [] : [LineDiscount::percentage(Percentage::fromString($line['discount_percent']))],
                $line['exemption'],
            ), $scenario->lines),
            null,
            $issueDate,
        );

        $lines = array_map(static fn ($line): AtCommunicableLine => new AtCommunicableLine(
            $line->taxRegion(),
            $line->taxCode(),
            $line->taxPercentage()->toString(),
            $line->exemptionReasonCode(),
            $line->netAmount()->toString(),
            $issueDate,
            [],
        ), $calculation->lines());

        $buckets = array_map(static fn ($entry): AtCommunicableTaxBucket => new AtCommunicableTaxBucket(
            $entry->taxRegion(),
            $entry->taxCode(),
            $entry->taxPercentage()->toString(),
            $entry->taxableBase()->toString(),
            $entry->taxAmount()->toString(),
        ), $calculation->taxSummary());

        $gross = $calculation->grossTotal()->toString();

        if ('gross_total_plus_one' === $scenario->tamper) {
            // A deliberately inconsistent document: this is corruption on purpose, not a calculation.
            $gross = BigDecimal::of($gross)->plus(1)->toScale(2)->toString();
        }

        return new AtCommunicableDocument(
            id: bin2hex(random_bytes(16)),
            documentType: $scenario->documentType,
            saftSection: $section,
            documentNo: \sprintf('%s %s/%d', $scenario->documentType, $series->code, $number),
            atcud: \sprintf('%s-%d', $series->validationCode, $number),
            issuerNif: $this->issuerNif,
            customerTaxId: $scenario->customerTaxId,
            customerCountry: $scenario->customerCountry,
            issueDate: $issueDate,
            systemEntryAt: $now,
            status: $scenario->status,
            statusAt: $now,
            // The suite sends as uncertified software (certificate number 0), for which AT wants "0".
            hashCharacters: '0',
            cashVatScheme: false,
            netTotal: $calculation->netTotal()->toString(),
            taxTotal: $calculation->taxTotal()->toString(),
            grossTotal: $gross,
            referencedDocumentNos: $referencedDocumentNos,
            lines: $lines,
            taxSummary: $buckets,
        );
    }
}
