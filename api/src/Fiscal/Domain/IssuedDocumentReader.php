<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Money;

/**
 * Read-only access to already-issued `documents`/`document_lines` — needed
 * by task 2.7's credit-note prefill and task 2.8's conversion prefill
 * (docs/plans/phase-2.md), not exposed as a full read API yet (task 2.11
 * builds the document list/detail screens). Raw DBAL like
 * {@see DocumentWriter}: neither table has a Doctrine entity.
 *
 * Each line's `pending_quantity` (task 2.8, §6.8 "the system tracks
 * converted quantities per source line") is `quantity` minus the sum of
 * `quantity` already claimed against that exact line number by any
 * non-cancelled document's `origin_references` — computed live, the same
 * "no current-accounts module yet" minimal running-total approach task
 * 2.7 already established for credit-note rectification.
 */
interface IssuedDocumentReader
{
    /**
     * @return array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string, lines: list<array{line_number: int, pending_quantity: string, product_id: ?string, product_code: string, product_description: string, product_type: string, unit_code: string, quantity: string, unit_price: string, discount_percent: ?string, tax_region: string, tax_code: string, exemption_reason_code: ?string}>}|null
     */
    public function find(CompanyId $companyId, DocumentId $id): ?array;

    /**
     * Same shape as {@see self::find()}, keyed by the human-readable
     * `document_no` rather than the internal id — `origin_references`
     * (like `document_references` before it, task 2.7) keys by
     * `document_no`, never by id.
     *
     * @return array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string, lines: list<array{line_number: int, pending_quantity: string, product_id: ?string, product_code: string, product_description: string, product_type: string, unit_code: string, quantity: string, unit_price: string, discount_percent: ?string, tax_region: string, tax_code: string, exemption_reason_code: ?string}>}|null
     */
    public function findByDocumentNo(CompanyId $companyId, string $documentNo): ?array;

    /**
     * Despacho 8632/2014 §3.3.7: a credit note cannot be issued against a
     * document already fully rectified. With no current-accounts module
     * yet (§6.7, later), "fully rectified" is judged here by summing the
     * `gross_total` of every non-cancelled NC already referencing this
     * document — the minimal running total this phase can track.
     */
    public function sumGrossTotalOfActiveCreditNotesAgainst(CompanyId $companyId, string $documentNo): Money;
}
