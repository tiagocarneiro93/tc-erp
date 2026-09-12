<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Exception\InvalidPriceAmount;
use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\Exception\ProductTaxRateNotFound;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use App\Shared\Domain\Tax\TaxRateConverter;
use Brick\Math\Exception\MathException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/plans/phase-1.md decision 2: display-only, never persisted — the
 * caller decides separately whether to store the entered value via
 * `SetProductPrice`.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class CalculateProductPriceConversionHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly TaxRateConverter $taxRateConverter,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
    ) {
    }

    public function __invoke(CalculateProductPriceConversion $query): PriceConversionResult
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.read', $companyId)) {
            throw new PermissionDenied();
        }

        $product = $this->products->find($companyId, $query->productId);

        if (null === $product) {
            throw new ProductNotFound();
        }

        try {
            $converted = $this->taxRateConverter->convertToOtherMode($product->taxRateId(), $query->amount, $query->includesVat);
        } catch (MathException) {
            throw new InvalidPriceAmount($query->amount);
        }

        if (null === $converted) {
            throw new ProductTaxRateNotFound();
        }

        return new PriceConversionResult($query->amount, $query->includesVat, $converted, !$query->includesVat);
    }
}
