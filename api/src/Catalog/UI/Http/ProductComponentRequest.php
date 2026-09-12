<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class ProductComponentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $quantity = '',
        public readonly int $sort_order = 0,
    ) {
    }
}
