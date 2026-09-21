<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\DocumentId;
use App\Fiscal\Domain\IssuedDocumentReader;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Money;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineIssuedDocumentReader implements IssuedDocumentReader
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function find(CompanyId $companyId, DocumentId $id): ?array
    {
        $document = $this->connection->fetchAssociative(
            'SELECT id, document_type, document_no, status, customer_id, pricing_mode, rounding_method, gross_total
             FROM documents WHERE company_id = ? AND id = ?',
            [$companyId->toString(), $id->toString()],
        );

        return $this->hydrate($companyId, $document);
    }

    public function findByDocumentNo(CompanyId $companyId, string $documentNo): ?array
    {
        $document = $this->connection->fetchAssociative(
            'SELECT id, document_type, document_no, status, customer_id, pricing_mode, rounding_method, gross_total
             FROM documents WHERE company_id = ? AND document_no = ?',
            [$companyId->toString(), $documentNo],
        );

        return $this->hydrate($companyId, $document);
    }

    /**
     * @param array<string, mixed>|false $document
     *
     * @return array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string, lines: list<array{line_number: int, pending_quantity: string, product_id: ?string, product_code: string, product_description: string, product_type: string, unit_code: string, quantity: string, unit_price: string, discount_percent: ?string, tax_region: string, tax_code: string, exemption_reason_code: ?string}>}|null
     */
    private function hydrate(CompanyId $companyId, array|false $document): ?array
    {
        if (false === $document) {
            return null;
        }

        // pending_quantity: this line's quantity minus whatever any other
        // (non-cancelled) document has already claimed against this exact
        // line number via its own lines' `origin_references` — task 2.8's
        // conversion tracking (§6.8). `jsonb_array_elements(NULL)` yields
        // zero rows rather than erroring, so ordinary, never-converted-from
        // lines (the vast majority) are untouched by the LATERAL join.
        $lines = $this->connection->fetchAllAssociative(
            "SELECT dl.line_number, dl.product_id, dl.product_code, dl.product_description, dl.product_type, dl.unit_code,
                    dl.quantity, dl.unit_price, dl.discount_percent, dl.tax_region, dl.tax_code, dl.exemption_reason_code,
                    dl.quantity - COALESCE((
                        SELECT SUM((oref->>'quantity')::numeric)
                        FROM document_lines tl
                        JOIN documents td ON td.company_id = tl.company_id AND td.id = tl.document_id
                        CROSS JOIN LATERAL jsonb_array_elements(tl.origin_references) AS oref
                        WHERE tl.company_id = dl.company_id
                          AND td.status <> 'A'
                          AND oref->>'document_no' = ?
                          AND (oref->>'line_number')::int = dl.line_number
                    ), 0) AS pending_quantity
             FROM document_lines dl WHERE dl.company_id = ? AND dl.document_id = ? ORDER BY dl.line_number ASC",
            [$document['document_no'], $companyId->toString(), $document['id']],
        );

        /** @var list<array{line_number: int, pending_quantity: string, product_id: ?string, product_code: string, product_description: string, product_type: string, unit_code: string, quantity: string, unit_price: string, discount_percent: ?string, tax_region: string, tax_code: string, exemption_reason_code: ?string}> $lineRows */
        $lineRows = $lines;

        /** @var array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string} $documentRow */
        $documentRow = $document;

        return $documentRow + ['lines' => $lineRows];
    }

    public function sumGrossTotalOfActiveCreditNotesAgainst(CompanyId $companyId, string $documentNo): Money
    {
        $sum = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(d.gross_total), 0.00)
             FROM document_references r
             JOIN documents d ON d.company_id = r.company_id AND d.id = r.document_id
             WHERE r.company_id = ? AND r.referenced_document_no = ? AND d.document_type = 'NC' AND d.status <> 'A'",
            [$companyId->toString(), $documentNo],
        );

        if (!\is_string($sum) && !is_numeric($sum)) {
            throw new \UnexpectedValueException('Unexpected column type reading document_references/documents.gross_total.');
        }

        return Money::fromString((string) $sum);
    }

    public function sumSettledAmount(CompanyId $companyId, DocumentId $documentId): Money
    {
        $sum = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(ra.settlement_amount), 0.00)
             FROM receipt_allocations ra
             JOIN receipts r ON r.company_id = ra.company_id AND r.id = ra.receipt_id
             WHERE ra.company_id = ? AND ra.document_id = ? AND r.status <> 'A'",
            [$companyId->toString(), $documentId->toString()],
        );

        if (!\is_string($sum) && !is_numeric($sum)) {
            throw new \UnexpectedValueException('Unexpected column type reading receipt_allocations/receipts.settlement_amount.');
        }

        return Money::fromString((string) $sum);
    }

    public function hasBlockingAtCommunication(CompanyId $companyId, DocumentId $documentId): bool
    {
        $found = $this->connection->fetchOne(
            "SELECT 1 FROM at_communications
             WHERE company_id = ? AND subject_type = 'Document' AND subject_id = ? AND status IN ('sending', 'accepted')
             LIMIT 1",
            [$companyId->toString(), $documentId->toString()],
        );

        return false !== $found;
    }
}
