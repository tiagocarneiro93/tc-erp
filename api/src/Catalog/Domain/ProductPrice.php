<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.4/§7.9.8: `amount` is stored exactly as entered —
 * never recomputed from a converted value — with `includesVat` recording
 * which mode it was entered in. Composite primary key
 * `(company_id, product_id, price_list_id)`, no surrogate id.
 */
final class ProductPrice
{
    private function __construct(
        private readonly CompanyId $companyId,
        private readonly ProductId $productId,
        private readonly PriceListId $priceListId,
        private string $amount,
        private bool $includesVat,
    ) {
    }

    public static function set(CompanyId $companyId, ProductId $productId, PriceListId $priceListId, string $amount, bool $includesVat): self
    {
        return new self($companyId, $productId, $priceListId, $amount, $includesVat);
    }

    public function update(string $amount, bool $includesVat): void
    {
        $this->amount = $amount;
        $this->includesVat = $includesVat;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function priceListId(): PriceListId
    {
        return $this->priceListId;
    }

    /**
     * The value exactly as entered — never a converted one (§7.9.8).
     */
    public function amount(): string
    {
        return $this->amount;
    }

    public function includesVat(): bool
    {
        return $this->includesVat;
    }
}
