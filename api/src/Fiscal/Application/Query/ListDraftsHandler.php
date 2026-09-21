<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\DocumentDraftRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListDraftsHandler
{
    public function __construct(
        private readonly DocumentDraftRepository $drafts,
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

        return array_map(
            DraftView::fromEntity(...),
            $this->drafts->findAll($companyId),
        );
    }
}
