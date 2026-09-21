<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\DocumentTypeRepository;
use App\Fiscal\Domain\Exception\DocumentDraftNotFound;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetDraftHandler
{
    public function __construct(
        private readonly DocumentDraftRepository $drafts,
        private readonly DocumentTypeRepository $documentTypes,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(GetDraft $query): DraftView
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.read', $companyId)) {
            throw new PermissionDenied();
        }

        $draft = $this->drafts->find($companyId, $query->draftId);

        if (null === $draft) {
            throw new DocumentDraftNotFound();
        }

        return DraftView::fromEntity($draft, $this->documentTypes->find($draft->documentType()));
    }
}
