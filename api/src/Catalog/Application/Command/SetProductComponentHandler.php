<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\InvalidQuantity;
use App\Catalog\Domain\Exception\NestedKitNotAllowed;
use App\Catalog\Domain\Exception\NotAKit;
use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\ProductComponent;
use App\Catalog\Domain\ProductComponentRepository;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Decimal\Quantity;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Brick\Math\Exception\MathException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Upsert (composite primary key `(company_id, kit_product_id,
 * component_product_id)`, task 1.8). Nesting is rejected outright per
 * technical-scope.md §7.10.1 — a kit containing itself is also caught here,
 * since the kit's own `kind` is `kit`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class SetProductComponentHandler
{
    public function __construct(
        private readonly ProductComponentRepository $components,
        private readonly ProductRepository $products,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(SetProductComponent $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $kit = $this->products->find($companyId, $command->kitProductId);

        if (null === $kit) {
            throw new ProductNotFound();
        }

        if ('kit' !== $kit->kind()) {
            throw new NotAKit();
        }

        $component = $this->products->find($companyId, $command->componentProductId);

        if (null === $component) {
            throw new ProductNotFound();
        }

        if ('kit' === $component->kind()) {
            throw new NestedKitNotAllowed();
        }

        try {
            $quantity = Quantity::fromString($command->quantity);
        } catch (MathException) {
            throw new InvalidQuantity($command->quantity);
        }

        if (!$quantity->isPositive()) {
            throw new InvalidQuantity($command->quantity);
        }

        $existing = $this->components->find($companyId, $command->kitProductId, $command->componentProductId);

        if (null === $existing) {
            $this->components->save(ProductComponent::set($companyId, $command->kitProductId, $command->componentProductId, $quantity, $command->sortOrder));
        } else {
            $existing->update($quantity, $command->sortOrder);
            $this->components->save($existing);
        }

        $this->auditLogger->log(
            'product_component.set',
            'ProductComponent',
            $command->kitProductId->toString(),
            ['component_product_id' => $command->componentProductId->toString(), 'quantity' => $command->quantity, 'sort_order' => $command->sortOrder],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
