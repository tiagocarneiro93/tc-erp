<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\Signing\NotAnInvoiceMention;
use App\Shared\Domain\Company\CompanyFiscalIdentityProvider;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Shared\Domain\Fiscal\PrintableLine;
use App\Shared\Domain\Fiscal\PrintableParty;
use App\Shared\Domain\Fiscal\PrintableTaxSummary;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stored data only (technical-scope.md §7.8, Despacho 8632/2014 §2.2.15): the
 * customer and issuer come from the snapshots frozen into the document, never
 * from `customers`/`company_profile`. Documents issued before the issuer
 * snapshot carried the company's full identity (`identity`, task 3.5) get the
 * company's *current* one — the only data left for them.
 */
final class DoctrinePrintableDocumentReader implements PrintableDocumentReader
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
        private readonly CompanyFiscalIdentityProvider $companies,
    ) {
    }

    public function find(CompanyId $companyId, string $documentId): ?PrintableDocument
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT d.id, d.document_type, d.document_no, d.atcud, d.issue_date, d.due_date, d.system_entry_at, d.status, d.status_reason,
                    d.is_training, d.template_version, d.hash, d.hash_control, d.qr_payload, d.currency, d.global_discount_percent,
                    d.settlement_total, d.net_total, d.tax_total, d.gross_total, d.payment_terms, d.customer_snapshot, d.issuer_snapshot,
                    t.name AS type_name, t.saft_section
             FROM documents d
             JOIN document_types t ON t.code = d.document_type
             WHERE d.company_id = ? AND d.id = ?',
            [$companyId->toString(), $documentId],
        );

        if (false === $row) {
            return null;
        }

        $issuer = $this->decodeJson($row['issuer_snapshot']);
        $customer = $this->decodeJson($row['customer_snapshot']);

        /** @var list<string> $references */
        $references = $this->connection->fetchFirstColumn(
            'SELECT referenced_document_no FROM document_references WHERE company_id = ? AND document_id = ? ORDER BY referenced_document_no',
            [$companyId->toString(), $documentId],
        );

        $isSigned = '' !== $this->string($row['hash']);

        return new PrintableDocument(
            id: $this->string($row['id']),
            documentType: $this->string($row['document_type']),
            documentTypeName: $this->string($row['type_name']),
            documentNo: $this->string($row['document_no']),
            atcud: $this->string($row['atcud']),
            issueDate: new \DateTimeImmutable($this->string($row['issue_date'])),
            dueDate: \is_string($row['due_date'] ?? null) ? new \DateTimeImmutable($row['due_date']) : null,
            systemEntryAt: new \DateTimeImmutable($this->string($row['system_entry_at'])),
            status: $this->string($row['status']),
            statusReason: $this->nullableString($row['status_reason']),
            isTraining: (bool) $row['is_training'],
            templateVersion: $this->string($row['template_version']),
            hashMention: $isSigned ? $this->string($row['hash_control']) : null,
            qrPayload: $this->string($row['qr_payload']),
            notAnInvoiceMention: 'WorkingDocuments' === $this->string($row['saft_section']) ? NotAnInvoiceMention::build() : null,
            currency: $this->string($row['currency']),
            globalDiscountPercent: $this->nullableString($row['global_discount_percent']),
            settlementTotal: $this->string($row['settlement_total']),
            netTotal: $this->string($row['net_total']),
            taxTotal: $this->string($row['tax_total']),
            grossTotal: $this->string($row['gross_total']),
            paymentTerms: $this->paymentTerms($row['payment_terms'] ?? null),
            issuer: $this->issuer($companyId, $issuer),
            customer: new PrintableParty(
                $this->stringOr($customer['name'] ?? null, 'Consumidor final'),
                $this->stringOr($customer['nif'] ?? null, '999999990'),
                $this->nullableString($customer['address'] ?? null),
                $this->nullableString($customer['postal_code'] ?? null),
                $this->nullableString($customer['city'] ?? null),
                $this->nullableString($customer['country'] ?? null),
            ),
            referencedDocumentNos: $references,
            lines: $this->lines($companyId, $documentId),
            taxSummary: $this->taxSummary($companyId, $documentId),
        );
    }

    /**
     * @param array<mixed> $issuerSnapshot
     */
    private function issuer(CompanyId $companyId, array $issuerSnapshot): PrintableParty
    {
        $frozen = $issuerSnapshot['identity'] ?? null;

        if (\is_array($frozen)) {
            return $this->partyFrom($frozen);
        }

        $current = $this->companies->forCompany($companyId);

        return new PrintableParty(
            $current->legalName ?? $this->stringOr($issuerSnapshot['name'] ?? null, ''),
            $current->nif ?? $this->stringOr($issuerSnapshot['nif'] ?? null, ''),
            $current?->address,
            $current?->postalCode,
            $current?->city,
            $current?->country,
            $current?->email,
            $current?->phone,
        );
    }

    /**
     * @param array<mixed> $identity
     */
    private function partyFrom(array $identity): PrintableParty
    {
        return new PrintableParty(
            $this->stringOr($identity['legal_name'] ?? null, ''),
            $this->stringOr($identity['nif'] ?? null, ''),
            $this->nullableString($identity['address'] ?? null),
            $this->nullableString($identity['postal_code'] ?? null),
            $this->nullableString($identity['city'] ?? null),
            $this->nullableString($identity['country'] ?? null),
            $this->nullableString($identity['email'] ?? null),
            $this->nullableString($identity['phone'] ?? null),
        );
    }

    /**
     * @return list<PrintableLine>
     */
    private function lines(CompanyId $companyId, string $documentId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT line_number, product_code, product_description, quantity, unit_code, unit_price, discount_percent, net_amount,
                    tax_percentage, tax_code, exemption_reason_code, exemption_reason_text, origin_references
             FROM document_lines WHERE company_id = ? AND document_id = ? ORDER BY line_number',
            [$companyId->toString(), $documentId],
        );

        $lines = [];

        foreach ($rows as $row) {
            $origins = [];

            if (\is_string($row['origin_references'] ?? null) && '' !== $row['origin_references']) {
                foreach ((array) json_decode($row['origin_references'], true, flags: \JSON_THROW_ON_ERROR) as $origin) {
                    if (\is_array($origin) && \is_string($origin['document_no'] ?? null)) {
                        $origins[$origin['document_no']] = $origin['document_no'];
                    }
                }
            }

            $lines[] = new PrintableLine(
                (int) $this->string($row['line_number']),
                $this->string($row['product_code']),
                $this->string($row['product_description']),
                $this->string($row['quantity']),
                $this->string($row['unit_code']),
                $this->string($row['unit_price']),
                $this->nullableString($row['discount_percent']),
                $this->string($row['net_amount']),
                $this->string($row['tax_percentage']),
                $this->string($row['tax_code']),
                $this->nullableString($row['exemption_reason_code']),
                $this->nullableString($row['exemption_reason_text']),
                array_values($origins),
            );
        }

        return $lines;
    }

    /**
     * @return list<PrintableTaxSummary>
     */
    private function taxSummary(CompanyId $companyId, string $documentId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT tax_region, tax_code, tax_percentage, taxable_base, tax_amount FROM document_tax_summary
             WHERE company_id = ? AND document_id = ? ORDER BY tax_region, tax_code',
            [$companyId->toString(), $documentId],
        );

        return array_map(fn (array $row): PrintableTaxSummary => new PrintableTaxSummary(
            $this->string($row['tax_region']),
            $this->string($row['tax_code']),
            $this->string($row['tax_percentage']),
            $this->string($row['taxable_base']),
            $this->string($row['tax_amount']),
        ), $rows);
    }

    /**
     * @return array<string, string>|null
     */
    private function paymentTerms(mixed $raw): ?array
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            return null;
        }

        $terms = [];

        foreach ($decoded as $key => $value) {
            if (\is_string($key) && (\is_string($value) || \is_int($value))) {
                $terms[$key] = (string) $value;
            }
        }

        return $terms;
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(mixed $raw): array
    {
        if (!\is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    private function string(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        throw new \UnexpectedValueException('Expected a scalar column value.');
    }

    private function nullableString(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function stringOr(mixed $value, string $default): string
    {
        return \is_string($value) && '' !== $value ? $value : $default;
    }
}
