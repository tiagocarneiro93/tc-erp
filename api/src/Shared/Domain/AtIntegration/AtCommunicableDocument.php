<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

/**
 * Everything the e-Fatura webservice needs from one issued document
 * (`Fatcorews.wsdl`'s `RegisterInvoice`/`RegisterWork` bodies), read from
 * stored data only — never recomputed (CLAUDE.md: all calculations go
 * through `PriceCalculator`; issued values are immutable). Produced by
 * Fiscal, consumed by `AtIntegration` (docs/plans/phase-3.md decision 2).
 */
final class AtCommunicableDocument
{
    /**
     * @param string                        $saftSection           `SalesInvoices` (→ `RegisterInvoice`) or `WorkingDocuments` (→ `RegisterWork`)
     * @param string                        $status                current `documents.status`: N|A|F
     * @param string                        $hashCharacters        the 1st/11th/21st/31st characters of the signature (`PrintedHashMention::fourCharacters`)
     * @param list<string>                  $referencedDocumentNos `document_references` — the invoice(s) a credit/debit note rectifies
     * @param list<AtCommunicableLine>      $lines
     * @param list<AtCommunicableTaxBucket> $taxSummary
     */
    public function __construct(
        public readonly string $id,
        public readonly string $documentType,
        public readonly string $saftSection,
        public readonly string $documentNo,
        public readonly string $atcud,
        public readonly string $issuerNif,
        public readonly string $customerTaxId,
        public readonly string $customerCountry,
        public readonly \DateTimeImmutable $issueDate,
        public readonly \DateTimeImmutable $systemEntryAt,
        public readonly string $status,
        public readonly \DateTimeImmutable $statusAt,
        public readonly string $hashCharacters,
        public readonly bool $cashVatScheme,
        public readonly string $netTotal,
        public readonly string $taxTotal,
        public readonly string $grossTotal,
        public readonly array $referencedDocumentNos,
        public readonly array $lines,
        public readonly array $taxSummary,
    ) {
    }
}
