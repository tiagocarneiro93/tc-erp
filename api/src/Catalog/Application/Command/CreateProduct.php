<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\ProductFamilyId;
use App\Catalog\Domain\ProductId;

final class CreateProduct
{
    public function __construct(
        public readonly ProductId $productId,
        public readonly string $actingUserId,
        public readonly string $code,
        public readonly string $description,
        public readonly string $type,
        public readonly string $kind,
        public readonly string $unitCode,
        public readonly ?string $barcode,
        public readonly ?ProductFamilyId $familyId,
        public readonly string $taxRateId,
        public readonly ?string $exemptionReasonCode,
        public readonly bool $trackStock,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
