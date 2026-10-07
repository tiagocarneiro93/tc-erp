<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

/**
 * An issued `SalesInvoices/Invoice` or `WorkingDocuments/WorkDocument`,
 * straight from `documents` + `document_lines` + `document_references`.
 */
final class SaftDocument
{
    /**
     * @param bool|null              $cashVatScheme         the regime frozen into the issuer snapshot, or null when the document predates it carrying one (the writer then uses the company's current regime)
     * @param list<string>           $referencedDocumentNos `document_references.referenced_document_no`
     * @param list<string|null>      $referenceReasons      parallel to $referencedDocumentNos (null reasons are not written)
     * @param list<SaftDocumentLine> $lines
     */
    public function __construct(
        public readonly string $documentType,
        public readonly string $documentNo,
        public readonly string $atcud,
        public readonly string $status,
        public readonly \DateTimeImmutable $statusAt,
        public readonly ?string $statusReason,
        public readonly string $sourceUserId,
        public readonly string $hash,
        public readonly \DateTimeImmutable $issueDate,
        public readonly \DateTimeImmutable $systemEntryAt,
        public readonly ?string $customerId,
        public readonly ?bool $cashVatScheme,
        public readonly string $taxTotal,
        public readonly string $netTotal,
        public readonly string $grossTotal,
        public readonly array $referencedDocumentNos,
        public readonly array $referenceReasons,
        public readonly array $lines,
    ) {
    }
}
