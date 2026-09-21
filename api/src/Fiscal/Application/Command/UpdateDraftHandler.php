<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\Exception\DocumentDraftNotFound;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\Tax\PriceCalculationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Recalculates on every update ("live, on every line change" —
 * docs/plans/phase-2.md task 2.4): whatever the web app sends as the
 * user edits a draft becomes both the new `payload` and, if calculable,
 * the new `calculated`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class UpdateDraftHandler
{
    public function __construct(
        private readonly DocumentDraftRepository $drafts,
        private readonly PriceCalculationService $priceCalculation,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(UpdateDraft $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        $draft = $this->drafts->find($companyId, $command->draftId);

        if (null === $draft) {
            throw new DocumentDraftNotFound();
        }

        $draft->updatePayload($command->payload, $this->priceCalculation->calculate($command->payload), $this->clock->now());
        $this->drafts->save($draft);

        $this->auditLogger->log(
            'document_draft.updated',
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
