<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\ProductFamilyCycleDetected;
use App\Catalog\Domain\Exception\ProductFamilyNotFound;
use App\Catalog\Domain\ProductFamilyId;
use App\Catalog\Domain\ProductFamilyRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateProductFamilyHandler
{
    public function __construct(
        private readonly ProductFamilyRepository $families,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(UpdateProductFamily $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $family = $this->families->find($companyId, $command->familyId);

        if (null === $family) {
            throw new ProductFamilyNotFound();
        }

        if (null !== $command->parentId) {
            if (null === $this->families->find($companyId, $command->parentId)) {
                throw new ProductFamilyNotFound();
            }

            $this->rejectCycle($companyId, $command->familyId, $command->parentId);
        }

        $family->rename($command->name, $command->parentId);
        $this->families->save($family);

        $this->auditLogger->log(
            'product_family.updated',
            'ProductFamily',
            $command->familyId->toString(),
            ['name' => $command->name],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    /**
     * Walks up from the proposed parent: if this family's own id is ever
     * reached, setting that parent would make the tree cyclic
     * (technical-scope.md §6.4).
     */
    private function rejectCycle(CompanyId $companyId, ProductFamilyId $familyId, ProductFamilyId $proposedParentId): void
    {
        $current = $proposedParentId;

        while (null !== $current) {
            if ($current->equals($familyId)) {
                throw new ProductFamilyCycleDetected();
            }

            $current = $this->families->find($companyId, $current)?->parentId();
        }
    }
}
