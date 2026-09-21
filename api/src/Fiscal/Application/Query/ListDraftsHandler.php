<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftRepository;
use App\Fiscal\Domain\DocumentTypeRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListDraftsHandler
{
    public function __construct(
        private readonly DocumentDraftRepository $drafts,
        private readonly DocumentTypeRepository $documentTypes,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<DraftView>
     */
    public function __invoke(ListDrafts $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.read', $companyId)) {
            throw new PermissionDenied();
        }

        $types = [];

        return array_map(
            function (DocumentDraft $draft) use (&$types): DraftView {
                $code = $draft->documentType();
                $types[$code] ??= $this->documentTypes->find($code);

                return DraftView::fromEntity($draft, $types[$code]);
            },
            $this->drafts->findAll($companyId),
        );
    }
}
