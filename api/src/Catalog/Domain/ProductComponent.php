<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Quantity;

/**
 * technical-scope.md §7.10.1: one row per (kit, component) pair, no
 * surrogate id — composite primary key `(company_id, kit_product_id,
 * component_product_id)`, so a kit lists a given component at most once.
 * Nesting (a component whose own `kind = kit`) is rejected outright by
 * `SetProductComponentHandler`, not here — checking it needs to look up
 * the component product, which this entity has no repository access to.
 */
final class ProductComponent
{
    private function __construct(
        private readonly CompanyId $companyId,
        private readonly ProductId $kitProductId,
        private readonly ProductId $componentProductId,
        private Quantity $quantity,
        private int $sortOrder,
    ) {
    }

    public static function set(CompanyId $companyId, ProductId $kitProductId, ProductId $componentProductId, Quantity $quantity, int $sortOrder): self
    {
        return new self($companyId, $kitProductId, $componentProductId, $quantity, $sortOrder);
    }

    public function update(Quantity $quantity, int $sortOrder): void
    {
        $this->quantity = $quantity;
        $this->sortOrder = $sortOrder;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function kitProductId(): ProductId
    {
        return $this->kitProductId;
    }

    public function componentProductId(): ProductId
    {
        return $this->componentProductId;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }
}
