<?php

declare(strict_types=1);

namespace App\Parties\Application\Query;

use App\Parties\Domain\Exception\SupplierNotFound;
use App\Parties\Domain\SupplierRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetSupplierHandler
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(GetSupplier $query): SupplierView
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('customers.read', $companyId)) {
            throw new PermissionDenied();
        }

        $supplier = $this->suppliers->find($companyId, $query->supplierId);

        if (null === $supplier) {
            throw new SupplierNotFound();
        }

        return SupplierView::fromEntity($supplier);
    }
}
