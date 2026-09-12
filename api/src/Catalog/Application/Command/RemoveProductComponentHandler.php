<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\ProductComponentNotFound;
use App\Catalog\Domain\ProductComponentRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RemoveProductComponentHandler
{
    public function __construct(
        private readonly ProductComponentRepository $components,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(RemoveProductComponent $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $component = $this->components->find($companyId, $command->kitProductId, $command->componentProductId);

        if (null === $component) {
            throw new ProductComponentNotFound();
        }

        $this->components->remove($component);

        $this->auditLogger->log(
            'product_component.removed',
            'ProductComponent',
            $command->kitProductId->toString(),
            ['component_product_id' => $command->componentProductId->toString()],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
