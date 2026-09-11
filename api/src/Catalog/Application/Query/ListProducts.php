<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\ProductFamilyId;

final class ListProducts
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?ProductFamilyId $familyId,
        public readonly ?bool $active,
        public readonly ?bool $trackStock,
    ) {
    }
}
