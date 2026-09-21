<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\ReceiptId;
use App\Fiscal\Domain\ReceiptReader;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineReceiptReader implements ReceiptReader
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function search(CompanyId $companyId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, document_no, atcud, issue_date,
                    COALESCE(customer_snapshot->>'name', 'Consumidor final') AS customer_name,
                    total, payment_method, status
             FROM receipts WHERE company_id = ? ORDER BY id ASC",
            [$companyId->toString()],
        );

        /** @var list<array{id: string, document_no: string, atcud: string, issue_date: string, customer_name: string, total: string, payment_method: string, status: string}> $receiptRows */
        $receiptRows = $rows;

        return $receiptRows;
    }

    public function find(CompanyId $companyId, ReceiptId $id): ?array
    {
        $receipt = $this->connection->fetchAssociative(
            "SELECT id, document_no, atcud, issue_date, customer_id,
                    COALESCE(customer_snapshot->>'name', 'Consumidor final') AS customer_name,
                    total, payment_method, status
             FROM receipts WHERE company_id = ? AND id = ?",
            [$companyId->toString(), $id->toString()],
        );

        if (false === $receipt) {
            return null;
        }

        $allocations = $this->connection->fetchAllAssociative(
            'SELECT ra.document_id, d.document_no, ra.amount, ra.settlement_amount
             FROM receipt_allocations ra
             JOIN documents d ON d.company_id = ra.company_id AND d.id = ra.document_id
             WHERE ra.company_id = ? AND ra.receipt_id = ?',
            [$companyId->toString(), $id->toString()],
        );

        /** @var list<array{document_id: string, document_no: string, amount: string, settlement_amount: string}> $allocationRows */
        $allocationRows = $allocations;

        /** @var array{id: string, document_no: string, atcud: string, issue_date: string, customer_id: ?string, customer_name: string, total: string, payment_method: string, status: string} $receiptRow */
        $receiptRow = $receipt;

        return $receiptRow + ['allocations' => $allocationRows];
    }
}
