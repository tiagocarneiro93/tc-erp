<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\IssuedDocumentReader;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListDocumentsHandler
{
    public function __construct(
        private readonly IssuedDocumentReader $documents,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<array{id: string, document_type: string, document_no: string, status: string, customer_id: ?string, customer_name: string, issue_date: string, gross_total: string, open_amount: string}>
     */
    public function __invoke(ListDocuments $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.read', $companyId)) {
            throw new PermissionDenied();
        }

        return $this->documents->search($companyId, $query->documentType, $query->status, $query->customerId, $query->from, $query->to);
    }
}
