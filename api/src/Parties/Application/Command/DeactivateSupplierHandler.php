<?php

declare(strict_types=1);

namespace App\Parties\Application\Command;

use App\Parties\Domain\Exception\SupplierNotFound;
use App\Parties\Domain\SupplierRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeactivateSupplierHandler
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(DeactivateSupplier $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('customers.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $supplier = $this->suppliers->find($companyId, $command->supplierId);

        if (null === $supplier) {
            throw new SupplierNotFound();
        }

        $supplier->deactivate($this->clock->now());
        $this->suppliers->save($supplier);

        $this->auditLogger->log(
            'supplier.deactivated',
            'Supplier',
            $command->supplierId->toString(),
            [],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
