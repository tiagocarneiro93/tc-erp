<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class ProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $code = '',
        #[Assert\NotBlank]
        public readonly string $description = '',
        #[Assert\NotBlank]
        public readonly string $type = '',
        // Create-only: fixed once the product exists, ignored by the
        // update endpoint (Product::update() has no such parameter).
        #[Assert\NotBlank]
        public readonly string $kind = 'simple',
        #[Assert\NotBlank]
        public readonly string $unit_code = '',
        public readonly ?string $barcode = null,
        public readonly ?string $family_id = null,
        #[Assert\NotBlank]
        public readonly string $tax_rate_id = '',
        public readonly ?string $exemption_reason_code = null,
        public readonly bool $track_stock = false,
    ) {
    }
}
