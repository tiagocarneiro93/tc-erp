<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\SupplierRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListSuppliersHandler
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<SupplierView>
     */
    public function __invoke(ListSuppliers $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('customers.read', $companyId)) {
            throw new PermissionDenied();
        }

        return array_map(
            SupplierView::fromEntity(...),
            $this->suppliers->search($companyId, $query->search),
        );
    }
}
