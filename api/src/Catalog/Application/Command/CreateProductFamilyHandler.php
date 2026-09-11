<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\ProductFamilyNotFound;
use App\Catalog\Domain\ProductFamily;
use App\Catalog\Domain\ProductFamilyRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateProductFamilyHandler
{
    public function __construct(
        private readonly ProductFamilyRepository $families,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(CreateProductFamily $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if (null !== $command->parentId && null === $this->families->find($companyId, $command->parentId)) {
            throw new ProductFamilyNotFound();
        }

        $this->families->save(ProductFamily::create($command->familyId, $companyId, $command->name, $command->parentId));

        $this->auditLogger->log(
            'product_family.created',
            'ProductFamily',
            $command->familyId->toString(),
            ['name' => $command->name],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
