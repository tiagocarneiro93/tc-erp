<?php

declare(strict_types=1);

namespace App\Shared\Domain\AtIntegration;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module port (docs/decisions/0004's pattern, docs/plans/phase-3.md
 * decision 2): lets `AtIntegration`'s outbox consumer read an issued
 * document's data without touching Fiscal's repositories (Deptrac forbids
 * it). Implemented by Fiscal. Read-only.
 */
interface AtCommunicableDocumentReader
{
    public function find(CompanyId $companyId, string $documentId): ?AtCommunicableDocument;
}
