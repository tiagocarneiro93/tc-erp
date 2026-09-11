<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\ProductFamilyId;

final class UpdateProductFamily
{
    public function __construct(
        public readonly ProductFamilyId $familyId,
        public readonly string $actingUserId,
        public readonly string $name,
        public readonly ?ProductFamilyId $parentId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
