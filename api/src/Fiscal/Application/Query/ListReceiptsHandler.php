<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Query;

use App\Fiscal\Domain\ReceiptReader;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListReceiptsHandler
{
    public function __construct(
        private readonly ReceiptReader $receipts,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<array{id: string, document_no: string, atcud: string, issue_date: string, customer_name: string, total: string, payment_method: string, status: string}>
     */
    public function __invoke(ListReceipts $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('documents.read', $companyId)) {
            throw new PermissionDenied();
        }

        return $this->receipts->search($companyId);
    }
}
