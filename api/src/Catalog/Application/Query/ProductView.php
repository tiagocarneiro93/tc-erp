<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Product;

final class ProductView
{
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $description,
        public readonly string $type,
        public readonly string $kind,
        public readonly string $unitCode,
        public readonly ?string $barcode,
        public readonly ?string $familyId,
        public readonly string $taxRateId,
        public readonly ?string $exemptionReasonCode,
        public readonly bool $trackStock,
        public readonly bool $active,
        public readonly ?string $lastCost,
        public readonly ?string $averageCost,
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        return new self(
            $product->id()->toString(),
            $product->code(),
            $product->description(),
            $product->type(),
            $product->kind(),
            $product->unitCode(),
            $product->barcode(),
            $product->familyId()?->toString(),
            $product->taxRateId(),
            $product->exemptionReasonCode(),
            $product->trackStock(),
            $product->active(),
            $product->lastCost(),
            $product->averageCost(),
        );
    }
}
