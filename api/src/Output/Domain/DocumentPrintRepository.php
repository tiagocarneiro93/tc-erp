<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\CompanyId;

/**
 * Insert-only (`document_prints`). Needs a company transaction.
 */
interface DocumentPrintRepository
{
    public function add(DocumentPrint $print): void;

    /**
     * Serialises concurrent prints of one document until the surrounding
     * transaction ends, so two simultaneous requests can never both be handed
     * the "Original" label.
     */
    public function lockDocument(CompanyId $companyId, string $documentId): void;

    public function countFor(CompanyId $companyId, string $documentId): int;

    /**
     * The copy label on the earliest row of this kind for the document.
     */
    public function firstLabelOf(CompanyId $companyId, string $documentId, DocumentPrintKind $kind): ?string;
}
