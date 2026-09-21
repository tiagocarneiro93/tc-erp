<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Money;

/**
 * Read-only access to already-issued `documents`/`document_lines` — needed
 * by task 2.7's credit-note prefill (docs/plans/phase-2.md), not exposed
 * as a full read API yet (task 2.11 builds the document list/detail
 * screens). Raw DBAL like {@see DocumentWriter}: neither table has a
 * Doctrine entity.
 */
interface IssuedDocumentReader
{
    /**
     * @return array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string, lines: list<array<string, mixed>>}|null
     */
    public function find(CompanyId $companyId, DocumentId $id): ?array;

    /**
     * Despacho 8632/2014 §3.3.7: a credit note cannot be issued against a
     * document already fully rectified. With no current-accounts module
     * yet (§6.7, later), "fully rectified" is judged here by summing the
     * `gross_total` of every non-cancelled NC already referencing this
     * document — the minimal running total this phase can track.
     */
    public function sumGrossTotalOfActiveCreditNotesAgainst(CompanyId $companyId, string $documentNo): Money;
}
