<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentConversionRules;
use App\Fiscal\Domain\DocumentTypeRepository;
use App\Fiscal\Domain\Exception\DocumentNotFound;
use App\Fiscal\Domain\IssuedDocumentReader;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Money;
use App\Shared\Domain\Decimal\Quantity;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-2.md task 2.11's own detail screen only ever gated its
 * action buttons by `document_type` (a static "is this kind of document
 * ever eligible" check) — never by this specific document's own state, so
 * a cancel/credit-note/convert button could show and then 422 on click.
 * Found by the owner testing the app: an FT that already had an active NC
 * against it still showed "Anular" too. `can_cancel`/`can_credit_note`/
 * `convert_targets` below answer "would the actual command succeed right
 * now", computed from exactly the same reader methods and rules
 * {@see \App\Fiscal\Application\Command\CancelDocumentHandler},
 * {@see \App\Fiscal\Application\Command\CreateCreditNoteDraftHandler} and
 * {@see \App\Fiscal\Application\Command\CreateConversionDraftHandler}
 * themselves check — never re-derived — so the web app can hide a button
 * instead of guessing (CLAUDE.md: no fiscal logic in the browser). Still
 * only a display convenience: a concurrent action between this read and
 * the click can still 422, and the command handlers remain the real,
 * re-checked authority either way.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class GetDocumentHandler
{
    public function __construct(
        private readonly IssuedDocumentReader $documents,
        private readonly DocumentTypeRepository $documentTypes,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, pricing_mode: string, rounding_method: string, gross_total: string, lines: list<array<string, mixed>>, can_cancel: bool, can_credit_note: bool, convert_targets: list<string>}
     */
    public function __invoke(GetDocument $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.read', $companyId)) {
            throw new PermissionDenied();
        }

        $document = $this->documents->find($companyId, $query->documentId);

        if (null === $document) {
            throw new DocumentNotFound();
        }

        return $document + [
            'can_cancel' => $this->canCancel($companyId, $query, $document),
            'can_credit_note' => $this->canCreditNote($companyId, $document),
            'convert_targets' => $this->convertTargets($document),
        ];
    }

    /**
     * @param array{status: string, document_no: string} $document
     */
    private function canCancel(CompanyId $companyId, GetDocument $query, array $document): bool
    {
        return 'N' === $document['status']
            && !$this->documents->sumGrossTotalOfActiveCreditNotesAgainst($companyId, $document['document_no'])->isPositive()
            && !$this->documents->sumSettledAmount($companyId, $query->documentId)->isPositive()
            && !$this->documents->hasBlockingAtCommunication($companyId, $query->documentId);
    }

    /**
     * Despacho 8632/2014 §3.3.7, mirroring {@see \App\Fiscal\Application\Command\CreateCreditNoteDraftHandler}:
     * status not `A`, not already fully rectified. Also requires
     * `account_effect === 'debit'` (FT/FS/ND) — the same restriction the
     * detail screen already applied client-side (only a sales invoice or
     * debit note is ever corrected with a credit note); the command
     * handler itself does not enforce this, which is a separate, narrower
     * gap than the one this task fixes and is left alone here.
     *
     * @param array{status: string, document_no: string, document_type: string, gross_total: string} $document
     */
    private function canCreditNote(CompanyId $companyId, array $document): bool
    {
        if ('A' === $document['status']) {
            return false;
        }

        if ('debit' !== $this->documentTypes->find($document['document_type'])?->accountEffect()) {
            return false;
        }

        $alreadyCredited = $this->documents->sumGrossTotalOfActiveCreditNotesAgainst($companyId, $document['document_no']);

        return $alreadyCredited->compareTo(Money::fromString($document['gross_total'])) < 0;
    }

    /**
     * Mirrors {@see \App\Fiscal\Application\Command\CreateConversionDraftHandler}:
     * a cancelled document converts to nothing; otherwise the allowed
     * targets for this source type, but only while at least one line still
     * has a positive `pending_quantity` — a fully converted working
     * document (§6.8) has none left, the same signal the handler's own
     * `nothingPending` check uses.
     *
     * @param array{status: string, document_type: string, lines: list<array{pending_quantity: string}>} $document
     *
     * @return list<string>
     */
    private function convertTargets(array $document): array
    {
        if ('A' === $document['status']) {
            return [];
        }

        $targets = DocumentConversionRules::allowedTargets($document['document_type']);

        if ([] === $targets) {
            return [];
        }

        foreach ($document['lines'] as $line) {
            if (Quantity::fromString($line['pending_quantity'])->isPositive()) {
                return $targets;
            }
        }

        return [];
    }
}
