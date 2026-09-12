<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListWarehousesHandler
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<WarehouseView>
     */
    public function __invoke(ListWarehouses $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('stock.read', $companyId)) {
            throw new PermissionDenied();
        }

        return array_map(
            WarehouseView::fromEntity(...),
            $this->warehouses->findAll($companyId),
        );
    }
}
