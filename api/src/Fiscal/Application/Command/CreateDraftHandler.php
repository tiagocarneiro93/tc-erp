<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\DocumentTypeRepository;
use App\Fiscal\Domain\Exception\UnknownDocumentType;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\Tax\PriceCalculationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateDraftHandler
{
    public function __construct(
        private readonly DocumentDraftRepository $drafts,
        private readonly DocumentTypeRepository $documentTypes,
        private readonly PriceCalculationService $priceCalculation,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateDraft $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.issue', $companyId)) {
            throw new PermissionDenied();
        }

        if (null === $this->documentTypes->find($command->documentType)) {
            throw new UnknownDocumentType($command->documentType);
        }

        $this->drafts->save(DocumentDraft::create(
            $command->draftId,
            $companyId,
            $command->documentType,
            $command->payload,
            $this->priceCalculation->calculate($command->payload),
            $command->actingUserId,
            $this->clock->now(),
        ));

        $this->auditLogger->log(
            'document_draft.created',
            'DocumentDraft',
            $command->draftId->toString(),
            ['document_type' => $command->documentType],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
