<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Exception\InvalidPriceAmount;
use App\Catalog\Domain\Exception\PriceListNotFound;
use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\PriceListRepository;
use App\Catalog\Domain\ProductPrice;
use App\Catalog\Domain\ProductPriceRepository;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Brick\Math\Exception\MathException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Upsert (the composite primary key is `(company_id, product_id, price_list_id)`
 * — task 1.7). `amount` is stored exactly as sent, never recomputed
 * (technical-scope.md §7.9.8).
 */
#[AsMessageHandler(bus: 'command.bus')]
final class SetProductPriceHandler
{
    public function __construct(
        private readonly ProductPriceRepository $prices,
        private readonly ProductRepository $products,
        private readonly PriceListRepository $priceLists,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(SetProductPrice $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('products.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if (null === $this->products->find($companyId, $command->productId)) {
            throw new ProductNotFound();
        }

        if (null === $this->priceLists->find($companyId, $command->priceListId)) {
            throw new PriceListNotFound();
        }

        try {
            Decimal::fromString($command->amount);
        } catch (MathException) {
            throw new InvalidPriceAmount($command->amount);
        }

        $existing = $this->prices->find($companyId, $command->productId, $command->priceListId);

        if (null === $existing) {
            $this->prices->save(ProductPrice::set($companyId, $command->productId, $command->priceListId, $command->amount, $command->includesVat));
        } else {
            $existing->update($command->amount, $command->includesVat);
            $this->prices->save($existing);
        }

        $this->auditLogger->log(
            'product_price.set',
            'ProductPrice',
            $command->productId->toString(),
            ['price_list_id' => $command->priceListId->toString(), 'amount' => $command->amount, 'includes_vat' => $command->includesVat],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
