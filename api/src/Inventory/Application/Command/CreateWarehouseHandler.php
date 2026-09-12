<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Warehouse;
use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reuses `stock.manage` (ADR 0002: "Adjustments, transfers, counts") —
 * warehouses are Inventory master data, gated by the same permission as
 * the rest of that area, rather than a new `warehouses.*` pair.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CreateWarehouseHandler
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(CreateWarehouse $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('stock.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if ($command->isDefault) {
            $this->unmarkCurrentDefault($companyId);
        }

        $this->warehouses->save(Warehouse::create(
            $command->warehouseId,
            $companyId,
            $command->code,
            $command->name,
            $command->address,
            $command->isDefault,
        ));

        $this->auditLogger->log(
            'warehouse.created',
            'Warehouse',
            $command->warehouseId->toString(),
            ['code' => $command->code, 'name' => $command->name],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    private function unmarkCurrentDefault(CompanyId $companyId): void
    {
        $currentDefault = $this->warehouses->findDefault($companyId);

        if (null !== $currentDefault) {
            $currentDefault->unmarkAsDefault();
            $this->warehouses->save($currentDefault);
        }
    }
}
