<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Exception\WarehouseNotFound;
use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeactivateWarehouseHandler
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(DeactivateWarehouse $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('stock.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $warehouse = $this->warehouses->find($companyId, $command->warehouseId);

        if (null === $warehouse) {
            throw new WarehouseNotFound();
        }

        $warehouse->deactivate();
        $this->warehouses->save($warehouse);

        $this->auditLogger->log(
            'warehouse.deactivated',
            'Warehouse',
            $command->warehouseId->toString(),
            [],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
