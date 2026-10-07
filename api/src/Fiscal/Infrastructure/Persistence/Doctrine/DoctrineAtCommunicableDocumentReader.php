<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\Signing\PrintedHashMention;
use App\Shared\Domain\AtIntegration\AtCommunicableDocument;
use App\Shared\Domain\AtIntegration\AtCommunicableDocumentReader;
use App\Shared\Domain\AtIntegration\AtCommunicableLine;
use App\Shared\Domain\AtIntegration\AtCommunicableTaxBucket;
use App\Shared\Domain\Company\CompanyFiscalIdentityProvider;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Raw DBAL like {@see DoctrineIssuedDocumentReader}: `documents` and
 * `document_lines` have no Doctrine entity. Reads stored values only —
 * `net_amount` per line, never a recomputation (CLAUDE.md).
 */
final class DoctrineAtCommunicableDocumentReader implements AtCommunicableDocumentReader
{
    /** Despacho 8632/2014 / Portaria 363/2010 — a consumer with no NIF is 999999990 ("Consumidor final"). */
    private const ANONYMOUS_CUSTOMER_NIF = '999999990';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
        private readonly CompanyFiscalIdentityProvider $companies,
    ) {
    }

    public function find(CompanyId $companyId, string $documentId): ?AtCommunicableDocument
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT d.id, d.document_type, d.document_no, d.atcud, d.issue_date, d.system_entry_at, d.status, d.status_at,
                    d.hash, d.customer_snapshot, d.issuer_snapshot, d.net_total, d.tax_total, d.gross_total, t.saft_section
             FROM documents d
             JOIN document_types t ON t.code = d.document_type
             WHERE d.company_id = ? AND d.id = ?',
            [$companyId->toString(), $documentId],
        );

        if (false === $row) {
            return null;
        }

        $customer = $this->decodeJson($row['customer_snapshot']);
        $issuer = $this->decodeJson($row['issuer_snapshot']);
        $issueDate = new \DateTimeImmutable($this->string($row['issue_date']));

        $lines = [];

        /** @var list<array<string, mixed>> $lineRows */
        $lineRows = $this->connection->fetchAllAssociative(
            'SELECT tax_region, tax_code, tax_percentage, exemption_reason_code, net_amount, tax_point_date, origin_references
             FROM document_lines
             WHERE company_id = ? AND document_id = ?
             ORDER BY line_number',
            [$companyId->toString(), $documentId],
        );

        foreach ($lineRows as $line) {
            $lines[] = new AtCommunicableLine(
                taxRegion: $this->string($line['tax_region']),
                taxCode: $this->string($line['tax_code']),
                taxPercentage: $this->string($line['tax_percentage']),
                exemptionReasonCode: \is_string($line['exemption_reason_code'] ?? null) ? $line['exemption_reason_code'] : null,
                netAmount: $this->string($line['net_amount']),
                taxPointDate: new \DateTimeImmutable(\is_string($line['tax_point_date'] ?? null) ? $line['tax_point_date'] : $issueDate->format('c')),
                originDocumentNos: $this->originDocumentNos($line['origin_references'] ?? null),
            );
        }

        $taxSummary = [];

        /** @var list<array<string, mixed>> $summaryRows */
        $summaryRows = $this->connection->fetchAllAssociative(
            'SELECT tax_region, tax_code, tax_percentage, taxable_base, tax_amount
             FROM document_tax_summary
             WHERE company_id = ? AND document_id = ?
             ORDER BY tax_region, tax_code',
            [$companyId->toString(), $documentId],
        );

        foreach ($summaryRows as $bucket) {
            $taxSummary[] = new AtCommunicableTaxBucket(
                $this->string($bucket['tax_region']),
                $this->string($bucket['tax_code']),
                $this->string($bucket['tax_percentage']),
                $this->string($bucket['taxable_base']),
                $this->string($bucket['tax_amount']),
            );
        }

        /** @var list<string> $references */
        $references = $this->connection->fetchFirstColumn(
            'SELECT referenced_document_no FROM document_references WHERE company_id = ? AND document_id = ? ORDER BY referenced_document_no',
            [$companyId->toString(), $documentId],
        );

        return new AtCommunicableDocument(
            id: $this->string($row['id']),
            documentType: $this->string($row['document_type']),
            saftSection: $this->string($row['saft_section']),
            documentNo: $this->string($row['document_no']),
            atcud: $this->string($row['atcud']),
            issuerNif: $this->stringOr($issuer['nif'] ?? null, ''),
            customerTaxId: $this->stringOr($customer['nif'] ?? null, self::ANONYMOUS_CUSTOMER_NIF),
            customerCountry: $this->stringOr($customer['country'] ?? null, 'PT'),
            issueDate: $issueDate,
            systemEntryAt: new \DateTimeImmutable($this->string($row['system_entry_at'])),
            status: $this->string($row['status']),
            statusAt: new \DateTimeImmutable($this->string($row['status_at'])),
            hashCharacters: PrintedHashMention::fourCharacters($this->string($row['hash'])),
            // Frozen at issuance (`issuer_snapshot.identity`, task 3.5); documents issued before that
            // carry none, and fall back to the company's *current* regime — right unless it changed since.
            cashVatScheme: $this->frozenCashVat($issuer) ?? ($this->companies->forCompany($companyId)->cashVat ?? false),
            netTotal: $this->string($row['net_total']),
            taxTotal: $this->string($row['tax_total']),
            grossTotal: $this->string($row['gross_total']),
            referencedDocumentNos: $references,
            lines: $lines,
            taxSummary: $taxSummary,
        );
    }

    /**
     * @param array<mixed> $issuerSnapshot
     */
    private function frozenCashVat(array $issuerSnapshot): ?bool
    {
        $identity = $issuerSnapshot['identity'] ?? null;

        return \is_array($identity) && \is_bool($identity['cash_vat'] ?? null) ? $identity['cash_vat'] : null;
    }

    /**
     * @return list<string>
     */
    private function originDocumentNos(mixed $raw): array
    {
        if (!\is_string($raw) || '' === $raw) {
            return [];
        }

        $documentNos = [];

        foreach ((array) json_decode($raw, true, flags: \JSON_THROW_ON_ERROR) as $origin) {
            if (\is_array($origin) && \is_string($origin['document_no'] ?? null)) {
                $documentNos[$origin['document_no']] = $origin['document_no'];
            }
        }

        return array_values($documentNos);
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

    private function stringOr(mixed $value, string $default): string
    {
        return \is_string($value) && '' !== $value ? $value : $default;
    }
}
