<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;

/**
 * Inserts the issued-receipt family in one call, mirroring
 * {@see DocumentWriter::insert()} — raw rows, not entities: neither
 * `receipts` nor `receipt_allocations` has a Doctrine mapping (task 2.3).
 * Must be called inside the same transaction as the series lock
 * ({@see \App\Shared\Domain\TransactionManager}).
 */
interface ReceiptWriter
{
    /**
     * `company_id` is added to every row internally — callers never pass
     * it themselves.
     *
     * @param array<string, mixed>       $receipt
     * @param list<array<string, mixed>> $allocations
     */
    public function insert(CompanyId $companyId, array $receipt, array $allocations): void;
}
