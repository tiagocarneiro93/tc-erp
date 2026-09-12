<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\ProductComponentRepository;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListProductComponentsHandler
{
    public function __construct(
        private readonly ProductComponentRepository $components,
        private readonly ProductRepository $products,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(ListProductComponents $query): KitComponentsView
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.read', $companyId)) {
            throw new PermissionDenied();
        }

        $kit = $this->products->find($companyId, $query->kitProductId);

        if (null === $kit) {
            throw new ProductNotFound();
        }

        $views = [];
        $estimatedCost = Decimal::fromString('0');

        foreach ($this->components->findAllForKit($companyId, $query->kitProductId) as $component) {
            $componentProduct = $this->products->find($companyId, $component->componentProductId());
            $averageCost = $componentProduct?->averageCost();

            $views[] = new ProductComponentView(
                $component->componentProductId()->toString(),
                $component->quantity()->toString(),
                $component->sortOrder(),
                null !== $componentProduct && $componentProduct->taxRateId() !== $kit->taxRateId(),
            );

            if (null === $averageCost) {
                $estimatedCost = null;
            } elseif (null !== $estimatedCost) {
                $lineCost = Decimal::fromBigDecimal($component->quantity()->toBigDecimal()->multipliedBy(Decimal::fromString($averageCost)->toBigDecimal()));
                $estimatedCost = $estimatedCost->plus($lineCost);
            }
        }

        return new KitComponentsView($views, $estimatedCost?->toString());
    }
}
