<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Domain\Exception\WarehouseNotFound;
use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetWarehouseHandler
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(GetWarehouse $query): WarehouseView
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('stock.read', $companyId)) {
            throw new PermissionDenied();
        }

        $warehouse = $this->warehouses->find($companyId, $query->warehouseId);

        if (null === $warehouse) {
            throw new WarehouseNotFound();
        }

        return WarehouseView::fromEntity($warehouse);
    }
}
