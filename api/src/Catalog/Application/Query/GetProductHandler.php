<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetProductHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(GetProduct $query): ProductView
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.read', $companyId)) {
            throw new PermissionDenied();
        }

        $product = $this->products->find($companyId, $query->productId);

        if (null === $product) {
            throw new ProductNotFound();
        }

        return ProductView::fromEntity($product);
    }
}
