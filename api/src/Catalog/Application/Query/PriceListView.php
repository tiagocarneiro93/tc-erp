<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\PriceList;

final class PriceListView
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $defaultIncludesVat,
    ) {
    }

    public static function fromEntity(PriceList $priceList): self
    {
        return new self($priceList->id()->toString(), $priceList->name(), $priceList->defaultIncludesVat());
    }
}
