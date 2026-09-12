<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Exception\WarehouseNotFound;
use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateWarehouseHandler
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(UpdateWarehouse $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('stock.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $warehouse = $this->warehouses->find($companyId, $command->warehouseId);

        if (null === $warehouse) {
            throw new WarehouseNotFound();
        }

        if ($command->isDefault) {
            $this->unmarkCurrentDefault($companyId, $command->warehouseId->toString());
        }

        $warehouse->update($command->code, $command->name, $command->address, $command->isDefault);
        $this->warehouses->save($warehouse);

        $this->auditLogger->log(
            'warehouse.updated',
            'Warehouse',
            $command->warehouseId->toString(),
            ['code' => $command->code, 'name' => $command->name],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    private function unmarkCurrentDefault(CompanyId $companyId, string $exceptWarehouseId): void
    {
        $currentDefault = $this->warehouses->findDefault($companyId);

        if (null !== $currentDefault && $currentDefault->id()->toString() !== $exceptWarehouseId) {
            $currentDefault->unmarkAsDefault();
            $this->warehouses->save($currentDefault);
        }
    }
}
