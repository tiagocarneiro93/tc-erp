<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\ProductPriceRepository;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListProductPricesHandler
{
    public function __construct(
        private readonly ProductPriceRepository $prices,
        private readonly ProductRepository $products,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<ProductPriceView>
     */
    public function __invoke(ListProductPrices $query): array
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.read', $companyId)) {
            throw new PermissionDenied();
        }

        if (null === $this->products->find($companyId, $query->productId)) {
            throw new ProductNotFound();
        }

        return array_map(ProductPriceView::fromEntity(...), $this->prices->findAllForProduct($companyId, $query->productId));
    }
}
