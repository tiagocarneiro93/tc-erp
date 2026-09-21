<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;

/**
 * Read-only access to already-issued `receipts`/`receipt_allocations` —
 * task 2.11's receipts list/detail screens. Raw DBAL like
 * {@see IssuedDocumentReader}: neither table has a Doctrine entity.
 */
interface ReceiptReader
{
    /**
     * @return list<array{id: string, document_no: string, atcud: string, issue_date: string, customer_name: string, total: string, payment_method: string, status: string}>
     */
    public function search(CompanyId $companyId): array;

    /**
     * @return array{id: string, document_no: string, atcud: string, issue_date: string, customer_id: ?string, customer_name: string, total: string, payment_method: string, status: string, allocations: list<array{document_id: string, document_no: string, amount: string, settlement_amount: string}>}|null
     */
    public function find(CompanyId $companyId, ReceiptId $id): ?array;
}
