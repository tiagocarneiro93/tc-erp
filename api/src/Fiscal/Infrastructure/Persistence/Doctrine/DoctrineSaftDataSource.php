<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\Saft\SaftCustomer;
use App\Fiscal\Domain\Saft\SaftDataSource;
use App\Fiscal\Domain\Saft\SaftDocument;
use App\Fiscal\Domain\Saft\SaftDocumentLine;
use App\Fiscal\Domain\Saft\SaftExportPeriod;
use App\Fiscal\Domain\Saft\SaftProduct;
use App\Fiscal\Domain\Saft\SaftReceipt;
use App\Fiscal\Domain\Saft\SaftReceiptLine;
use App\Fiscal\Domain\Saft\SaftSectionTotals;
use App\Fiscal\Domain\Saft\SaftTaxEntry;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Tax\ExemptionReasonTextProvider;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Raw DBAL (none of these tables has an entity). Documents are read in
 * keyset-paginated chunks ordered by when they were entered into the system,
 * with their lines/references fetched per chunk — constant memory however
 * many documents the period holds, and no long-lived server-side cursor.
 *
 * Section membership comes from `document_types.saft_section`, never from a
 * hard-coded list of document codes.
 */
final class DoctrineSaftDataSource implements SaftDataSource
{
    private const CHUNK_SIZE = 200;
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:sP';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
        private readonly ExemptionReasonTextProvider $exemptionReasons,
    ) {
    }

    public function customers(CompanyId $companyId, SaftExportPeriod $period): iterable
    {
        /** @var list<array{customer_id: ?string, customer_snapshot: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT ON (key) customer_id, customer_snapshot FROM (
                SELECT COALESCE(customer_id::text, \'\') AS key, customer_id, customer_snapshot, issue_date
                FROM documents WHERE company_id = :company AND issue_date >= :from AND issue_date < :until
                UNION ALL
                SELECT COALESCE(customer_id::text, \'\') AS key, customer_id, customer_snapshot, issue_date
                FROM receipts WHERE company_id = :company AND issue_date >= :from AND issue_date < :until
             ) AS referenced
             ORDER BY key, issue_date DESC',
            $this->periodParameters($companyId, $period),
        );

        foreach ($rows as $row) {
            $snapshot = $this->decodeJson($row['customer_snapshot']);

            yield new SaftCustomer(
                $row['customer_id'] ?? '',
                $this->stringOr($snapshot['nif'] ?? null, '999999990'),
                $this->stringOr($snapshot['name'] ?? null, 'Consumidor final'),
                $this->nullableString($snapshot['address'] ?? null),
                $this->nullableString($snapshot['postal_code'] ?? null),
                $this->nullableString($snapshot['city'] ?? null),
                $this->nullableString($snapshot['country'] ?? null),
            );
        }
    }

    public function products(CompanyId $companyId, SaftExportPeriod $period): iterable
    {
        /** @var list<array{product_code: string, product_description: string, product_type: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT ON (l.product_code) l.product_code, l.product_description, l.product_type
             FROM document_lines l
             JOIN documents d ON d.company_id = l.company_id AND d.id = l.document_id
             WHERE d.company_id = :company AND d.issue_date >= :from AND d.issue_date < :until
             ORDER BY l.product_code, d.issue_date DESC',
            $this->periodParameters($companyId, $period),
        );

        foreach ($rows as $row) {
            yield new SaftProduct($row['product_type'], $row['product_code'], $row['product_description']);
        }
    }

    public function taxEntries(CompanyId $companyId, SaftExportPeriod $period): array
    {
        /** @var list<array{tax_region: string, tax_code: string, tax_percentage: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT s.tax_region, s.tax_code, s.tax_percentage
             FROM document_tax_summary s
             JOIN documents d ON d.company_id = s.company_id AND d.id = s.document_id
             WHERE d.company_id = :company AND d.issue_date >= :from AND d.issue_date < :until
             ORDER BY s.tax_region, s.tax_code, s.tax_percentage',
            $this->periodParameters($companyId, $period),
        );

        return array_map(
            static fn (array $row): SaftTaxEntry => new SaftTaxEntry($row['tax_region'], $row['tax_code'], $row['tax_percentage']),
            $rows,
        );
    }

    public function documentTotals(CompanyId $companyId, SaftExportPeriod $period, string $section): SaftSectionTotals
    {
        /** @var array{entries: int|string, debit: string, credit: string} $row */
        $row = $this->connection->fetchAssociative(
            "SELECT count(*) AS entries,
                    COALESCE(SUM(lines.net) FILTER (WHERE d.status <> 'A' AND d.document_type = 'NC'), 0) AS debit,
                    COALESCE(SUM(lines.net) FILTER (WHERE d.status <> 'A' AND d.document_type <> 'NC'), 0) AS credit
             FROM documents d
             JOIN document_types t ON t.code = d.document_type AND t.saft_section = :section
             LEFT JOIN LATERAL (
               SELECT SUM(l.net_amount) AS net FROM document_lines l WHERE l.company_id = d.company_id AND l.document_id = d.id
             ) lines ON true
             WHERE d.company_id = :company AND d.issue_date >= :from AND d.issue_date < :until",
            $this->periodParameters($companyId, $period) + ['section' => $section],
        );

        return new SaftSectionTotals((int) $row['entries'], $row['debit'], $row['credit']);
    }

    public function documents(CompanyId $companyId, SaftExportPeriod $period, string $section): iterable
    {
        $cursor = null;

        do {
            $parameters = $this->periodParameters($companyId, $period) + ['section' => $section, 'limit' => self::CHUNK_SIZE];
            $keyset = '';

            if (null !== $cursor) {
                $keyset = 'AND (d.system_entry_at, d.id) > (:afterEntry, :afterId)';
                $parameters += ['afterEntry' => $cursor['entry'], 'afterId' => $cursor['id']];
            }

            /** @var list<array<string, mixed>> $chunk */
            $chunk = $this->connection->fetchAllAssociative(
                "SELECT d.id, d.document_type, d.document_no, d.atcud, d.status, d.status_at, d.status_reason, d.source_id,
                        d.hash, d.issue_date, d.system_entry_at, d.customer_id, d.issuer_snapshot,
                        d.tax_total, d.net_total, d.gross_total
                 FROM documents d
                 JOIN document_types t ON t.code = d.document_type AND t.saft_section = :section
                 WHERE d.company_id = :company AND d.issue_date >= :from AND d.issue_date < :until {$keyset}
                 ORDER BY d.system_entry_at, d.id
                 LIMIT :limit",
                $parameters,
            );

            if ([] === $chunk) {
                return;
            }

            $ids = array_map(fn (array $row): string => $this->string($row['id']), $chunk);
            $lines = $this->linesByDocument($companyId, $ids);
            $references = $this->referencesByDocument($companyId, $ids);

            foreach ($chunk as $row) {
                $id = $this->string($row['id']);
                $issuer = $this->decodeJson($row['issuer_snapshot']);
                $systemEntryAt = new \DateTimeImmutable($this->string($row['system_entry_at']));

                yield new SaftDocument(
                    $this->string($row['document_type']),
                    $this->string($row['document_no']),
                    $this->string($row['atcud']),
                    $this->string($row['status']),
                    new \DateTimeImmutable($this->string($row['status_at'])),
                    $this->nullableString($row['status_reason']),
                    $this->string($row['source_id']),
                    $this->string($row['hash']),
                    new \DateTimeImmutable($this->string($row['issue_date'])),
                    $systemEntryAt,
                    $this->nullableString($row['customer_id']),
                    \is_array($issuer['identity'] ?? null) && \is_bool($issuer['identity']['cash_vat'] ?? null) ? $issuer['identity']['cash_vat'] : null,
                    $this->string($row['tax_total']),
                    $this->string($row['net_total']),
                    $this->string($row['gross_total']),
                    array_column($references[$id] ?? [], 'no'),
                    array_column($references[$id] ?? [], 'reason'),
                    $lines[$id] ?? [],
                );
            }

            $last = $chunk[array_key_last($chunk)];
            $cursor = [
                'entry' => (new \DateTimeImmutable($this->string($last['system_entry_at'])))->format(self::TIMESTAMP_FORMAT),
                'id' => $this->string($last['id']),
            ];
        } while (self::CHUNK_SIZE === \count($chunk));
    }

    public function receiptTotals(CompanyId $companyId, SaftExportPeriod $period): SaftSectionTotals
    {
        /** @var array{entries: int|string, credit: string} $row */
        $row = $this->connection->fetchAssociative(
            "SELECT count(*) AS entries,
                    COALESCE(SUM(allocations.total) FILTER (WHERE r.status <> 'A'), 0) AS credit
             FROM receipts r
             LEFT JOIN LATERAL (
               SELECT SUM(a.amount) AS total FROM receipt_allocations a WHERE a.company_id = r.company_id AND a.receipt_id = r.id
             ) allocations ON true
             WHERE r.company_id = :company AND r.issue_date >= :from AND r.issue_date < :until",
            $this->periodParameters($companyId, $period),
        );

        return new SaftSectionTotals((int) $row['entries'], '0.00', $row['credit']);
    }

    public function receipts(CompanyId $companyId, SaftExportPeriod $period): iterable
    {
        $cursor = null;

        do {
            $parameters = $this->periodParameters($companyId, $period) + ['limit' => self::CHUNK_SIZE];
            $keyset = '';

            if (null !== $cursor) {
                $keyset = 'AND (r.system_entry_at, r.id) > (:afterEntry, :afterId)';
                $parameters += ['afterEntry' => $cursor['entry'], 'afterId' => $cursor['id']];
            }

            /** @var list<array<string, mixed>> $chunk */
            $chunk = $this->connection->fetchAllAssociative(
                "SELECT r.id, r.document_no, r.atcud, r.status, r.status_at, r.status_reason, r.source_id,
                        r.issue_date, r.system_entry_at, r.customer_id, r.payment_method, r.total
                 FROM receipts r
                 WHERE r.company_id = :company AND r.issue_date >= :from AND r.issue_date < :until {$keyset}
                 ORDER BY r.system_entry_at, r.id
                 LIMIT :limit",
                $parameters,
            );

            if ([] === $chunk) {
                return;
            }

            $ids = array_map(fn (array $row): string => $this->string($row['id']), $chunk);
            $allocations = $this->allocationsByReceipt($companyId, $ids);

            foreach ($chunk as $row) {
                yield new SaftReceipt(
                    $this->string($row['document_no']),
                    $this->string($row['atcud']),
                    $this->string($row['status']),
                    new \DateTimeImmutable($this->string($row['status_at'])),
                    $this->nullableString($row['status_reason']),
                    $this->string($row['source_id']),
                    new \DateTimeImmutable($this->string($row['issue_date'])),
                    new \DateTimeImmutable($this->string($row['system_entry_at'])),
                    $this->nullableString($row['customer_id']),
                    $this->string($row['payment_method']),
                    $this->string($row['total']),
                    $allocations[$this->string($row['id'])] ?? [],
                );
            }

            $last = $chunk[array_key_last($chunk)];
            $cursor = [
                'entry' => (new \DateTimeImmutable($this->string($last['system_entry_at'])))->format(self::TIMESTAMP_FORMAT),
                'id' => $this->string($last['id']),
            ];
        } while (self::CHUNK_SIZE === \count($chunk));
    }

    /**
     * @param list<string> $documentIds
     *
     * @return array<string, list<SaftDocumentLine>>
     */
    private function linesByDocument(CompanyId $companyId, array $documentIds): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.document_id, l.line_number, l.product_code, l.product_description, l.quantity, l.unit_code, l.unit_price,
                    l.discount_amount, l.settlement_amount, l.net_amount, l.tax_region, l.tax_code, l.tax_percentage,
                    l.exemption_reason_code, l.exemption_reason_text, l.tax_point_date, l.origin_references, d.issue_date
             FROM document_lines l
             JOIN documents d ON d.company_id = l.company_id AND d.id = l.document_id
             WHERE l.company_id = ? AND l.document_id IN (?)
             ORDER BY l.document_id, l.line_number',
            [$companyId->toString(), $documentIds],
            [1 => ArrayParameterType::STRING],
        );

        $byDocument = [];

        foreach ($rows as $row) {
            $issueDate = new \DateTimeImmutable($this->string($row['issue_date']));
            $originNos = [];

            if (\is_string($row['origin_references'] ?? null) && '' !== $row['origin_references']) {
                foreach ((array) json_decode($row['origin_references'], true, flags: \JSON_THROW_ON_ERROR) as $origin) {
                    if (\is_array($origin) && \is_string($origin['document_no'] ?? null)) {
                        $originNos[$origin['document_no']] = $origin['document_no'];
                    }
                }
            }

            $byDocument[$this->string($row['document_id'])][] = new SaftDocumentLine(
                (int) $this->string($row['line_number']),
                $this->string($row['product_code']),
                $this->string($row['product_description']),
                $this->string($row['quantity']),
                $this->string($row['unit_code']),
                $this->string($row['unit_price']),
                $this->string($row['discount_amount']),
                $this->string($row['settlement_amount']),
                $this->string($row['net_amount']),
                $this->string($row['tax_region']),
                $this->string($row['tax_code']),
                $this->string($row['tax_percentage']),
                $this->nullableString($row['exemption_reason_code']),
                // Lines issued before issuance froze the wording (task 3.3) carry none: fall back to AT's table.
                $this->nullableString($row['exemption_reason_text']) ?? (null !== $this->nullableString($row['exemption_reason_code']) ? $this->exemptionReasons->wordingFor($this->string($row['exemption_reason_code'])) : null),
                \is_string($row['tax_point_date'] ?? null) ? new \DateTimeImmutable($row['tax_point_date']) : $issueDate,
                array_values($originNos),
            );
        }

        return $byDocument;
    }

    /**
     * @param list<string> $documentIds
     *
     * @return array<string, list<array{no: string, reason: ?string}>>
     */
    private function referencesByDocument(CompanyId $companyId, array $documentIds): array
    {
        /** @var list<array{document_id: string, referenced_document_no: string, reason: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT document_id, referenced_document_no, reason FROM document_references
             WHERE company_id = ? AND document_id IN (?) ORDER BY document_id, referenced_document_no',
            [$companyId->toString(), $documentIds],
            [1 => ArrayParameterType::STRING],
        );

        $byDocument = [];

        foreach ($rows as $row) {
            $byDocument[$row['document_id']][] = ['no' => $row['referenced_document_no'], 'reason' => $row['reason']];
        }

        return $byDocument;
    }

    /**
     * @param list<string> $receiptIds
     *
     * @return array<string, list<SaftReceiptLine>>
     */
    private function allocationsByReceipt(CompanyId $companyId, array $receiptIds): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.receipt_id, a.amount, a.settlement_amount, d.document_no, d.issue_date
             FROM receipt_allocations a
             JOIN documents d ON d.company_id = a.company_id AND d.id = a.document_id
             WHERE a.company_id = ? AND a.receipt_id IN (?)
             ORDER BY a.receipt_id, d.system_entry_at, d.id',
            [$companyId->toString(), $receiptIds],
            [1 => ArrayParameterType::STRING],
        );

        $byReceipt = [];

        foreach ($rows as $row) {
            $receiptId = $this->string($row['receipt_id']);
            $byReceipt[$receiptId][] = new SaftReceiptLine(
                \count($byReceipt[$receiptId] ?? []) + 1,
                $this->string($row['document_no']),
                new \DateTimeImmutable($this->string($row['issue_date'])),
                $this->string($row['amount']),
                $this->string($row['settlement_amount']),
            );
        }

        return $byReceipt;
    }

    /**
     * @return array{company: string, from: string, until: string}
     */
    private function periodParameters(CompanyId $companyId, SaftExportPeriod $period): array
    {
        return [
            'company' => $companyId->toString(),
            'from' => $period->from()->format(self::TIMESTAMP_FORMAT),
            'until' => $period->until()->format(self::TIMESTAMP_FORMAT),
        ];
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
