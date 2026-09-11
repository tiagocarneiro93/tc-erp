<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\ProductFamily;

final class ProductFamilyView
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $parentId,
    ) {
    }

    public static function fromEntity(ProductFamily $family): self
    {
        return new self(
            $family->id()->toString(),
            $family->name(),
            $family->parentId()?->toString(),
        );
    }
}
