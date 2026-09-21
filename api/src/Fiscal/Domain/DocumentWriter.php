<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;

/**
 * Inserts the issued-document family in one call (§7.1 step 10:
 * "INSERT document, lines, tax summary, references, status event") — raw
 * rows, not entities: `documents`/`document_lines`/`document_tax_summary`/
 * `document_references`/`document_status_events` have no Doctrine mapping
 * (docs/plans/phase-2.md task 2.3's own note — nothing reads them back
 * through the ORM yet, only through dedicated read models when those
 * exist). Must be called inside the same transaction as the series lock
 * ({@see \App\Shared\Domain\TransactionManager}), so a failure anywhere
 * here rolls back the number/hash the series was about to record too.
 */
interface DocumentWriter
{
    /**
     * `company_id` is added to every row internally — callers never pass
     * it themselves.
     *
     * @param array<string, mixed>       $document
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $taxSummary
     * @param list<array<string, mixed>> $references
     * @param array<string, mixed>       $statusEvent
     */
    public function insert(CompanyId $companyId, array $document, array $lines, array $taxSummary, array $references, array $statusEvent): void;
}
