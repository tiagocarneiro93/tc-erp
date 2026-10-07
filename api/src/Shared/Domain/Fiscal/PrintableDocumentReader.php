<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module port (docs/decisions/0004's pattern): Output renders PDFs of
 * documents it must not read from Fiscal's tables itself. Only *issued*
 * documents exist behind it — a draft has no `documents` row, so it can never
 * be printed or sent as a document (CLAUDE.md hard rule).
 */
interface PrintableDocumentReader
{
    public function find(CompanyId $companyId, string $documentId): ?PrintableDocument;
}
