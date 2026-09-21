<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\Exception\DocumentDraftNotFound;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A real DELETE, not a status flag: unlike issued documents, a draft has
 * no fiscal weight (CLAUDE.md — "Drafts are not documents"), so nothing
 * about deleting one needs to be reversible or auditable beyond the
 * ordinary audit log entry.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class DeleteDraftHandler
{
    public function __construct(
        private readonly DocumentDraftRepository $drafts,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(DeleteDraft $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        $draft = $this->drafts->find($companyId, $command->draftId);

        if (null === $draft) {
            throw new DocumentDraftNotFound();
        }

        $this->drafts->remove($draft);

        $this->auditLogger->log(
            'document_draft.deleted',
            'DocumentDraft',
            $command->draftId->toString(),
            [],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
