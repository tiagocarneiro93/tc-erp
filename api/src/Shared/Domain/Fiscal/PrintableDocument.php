<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

/**
 * Everything a PDF of an issued document may show, read from stored data only
 * (docs/plans/phase-3.md task 3.5; technical-scope.md §7.8: "never a live join
 * to customers/company_profile, so a later address/logo/name change never
 * alters how an old document prints"). The same document always yields the
 * same `PrintableDocument`.
 */
final class PrintableDocument
{
    /**
     * @param string|null                $hashMention           Despacho 8632/2014 §2.2.2's "AxAx-Processado por programa certificado n.º …/AT"; null for an unsigned type
     * @param string|null                $notAnInvoiceMention   §1.2 — only on working documents
     * @param array<string, string>|null $paymentTerms          the stored `payment_terms` JSON, if any
     * @param list<string>               $referencedDocumentNos the invoice(s) a credit/debit note rectifies
     * @param list<PrintableLine>        $lines
     * @param list<PrintableTaxSummary>  $taxSummary
     */
    public function __construct(
        public readonly string $id,
        public readonly string $documentType,
        public readonly string $documentTypeName,
        public readonly string $documentNo,
        public readonly string $atcud,
        public readonly \DateTimeImmutable $issueDate,
        public readonly ?\DateTimeImmutable $dueDate,
        public readonly \DateTimeImmutable $systemEntryAt,
        public readonly string $status,
        public readonly ?string $statusReason,
        public readonly bool $isTraining,
        public readonly string $templateVersion,
        public readonly ?string $hashMention,
        public readonly string $qrPayload,
        public readonly ?string $notAnInvoiceMention,
        public readonly string $currency,
        public readonly ?string $globalDiscountPercent,
        public readonly string $settlementTotal,
        public readonly string $netTotal,
        public readonly string $taxTotal,
        public readonly string $grossTotal,
        public readonly ?array $paymentTerms,
        public readonly PrintableParty $issuer,
        public readonly PrintableParty $customer,
        public readonly array $referencedDocumentNos,
        public readonly array $lines,
        public readonly array $taxSummary,
    ) {
    }
}
