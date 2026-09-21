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

        if (false === $document) {
            return null;
        }

        $lines = $this->connection->fetchAllAssociative(
            'SELECT product_id, product_code, product_description, product_type, unit_code, quantity, unit_price,
                    discount_percent, tax_region, tax_code, exemption_reason_code
             FROM document_lines WHERE company_id = ? AND document_id = ? ORDER BY line_number ASC',
            [$companyId->toString(), $id->toString()],
        );

        /** @var array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string} $documentRow */
        $documentRow = $document;

        return $documentRow + ['lines' => $lines];
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
}
