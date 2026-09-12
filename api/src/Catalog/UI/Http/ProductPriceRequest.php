<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class ProductPriceRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $amount = '',
        public readonly bool $includes_vat = false,
    ) {
    }
}
