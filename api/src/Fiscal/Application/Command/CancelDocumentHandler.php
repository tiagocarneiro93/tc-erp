<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentWriter;
use App\Fiscal\Domain\Exception\CancellationNotAllowed;
use App\Fiscal\Domain\Exception\DocumentNotFound;
use App\Fiscal\Domain\IssuedDocumentReader;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\TransactionManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * docs/plans/phase-2.md task 2.10, `docs/legal/civa-extracts.md`'s "What
 * this resolves" note: status `A`, only for a document that never had
 * external effect — no active NC already rectifying it (Despacho
 * 8632/2014 §3.3.8), not already settled by an active receipt (added
 * after the owner tested the web app: paying an invoice is at least as
 * strong a signal it reached the customer as AT communication is — you
 * cannot pay an invoice you never received), and not (plausibly) yet
 * delivered to the customer, approximated here by "not yet communicated
 * to the AT" (CIVA Art. 29.º §7). Writes the `document_status_events` row
 * task 2.3's own trigger requires before allowing the `documents.status`
 * change, via the same {@see DocumentWriter::updateStatus()} task 2.8's
 * conversion-closing already added. Stock/account compensating entries
 * would reverse this cancellation's effects here, same as task 2.6 leaves
 * issuance's own side effects unwired — neither stock movements (Phase 5)
 * nor current accounts (§6.7) exist yet, so there is nothing to
 * compensate yet.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CancelDocumentHandler
{
    public function __construct(
        private readonly IssuedDocumentReader $documents,
        private readonly DocumentWriter $documentWriter,
        private readonly TransactionManager $transactions,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CancelDocument $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.cancel', $companyId)) {
            throw new PermissionDenied();
        }

        $document = $this->documents->find($companyId, $command->documentId);

        if (null === $document) {
            throw new DocumentNotFound();
        }

        if ('N' !== $document['status']) {
            throw CancellationNotAllowed::documentNotActive($document['status']);
        }

        if ($this->documents->sumGrossTotalOfActiveCreditNotesAgainst($companyId, $document['document_no'])->isPositive()) {
            throw CancellationNotAllowed::alreadyRectified();
        }

        if ($this->documents->sumSettledAmount($companyId, $command->documentId)->isPositive()) {
            throw CancellationNotAllowed::alreadySettledByAReceipt();
        }

        if ($this->documents->hasBlockingAtCommunication($companyId, $command->documentId)) {
            throw CancellationNotAllowed::mayAlreadyHaveReachedTheCustomer();
        }

        $now = $this->clock->now();

        $this->transactions->transactional(function () use ($companyId, $command, $now): void {
            $statusEvent = [
                'id' => Uuid::v7()->toRfc4122(),
                'document_id' => $command->documentId->toString(),
                'status' => 'A',
                'reason' => $command->reason,
                'user_id' => $command->actingUserId,
                'occurred_at' => $now->format('Y-m-d H:i:sP'),
            ];

            $this->documentWriter->updateStatus($companyId, $command->documentId, 'A', $command->reason, $now, $statusEvent);
        });

        $this->auditLogger->log(
            'document.cancelled',
            'Document',
            $command->documentId->toString(),
            ['reason' => $command->reason],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
