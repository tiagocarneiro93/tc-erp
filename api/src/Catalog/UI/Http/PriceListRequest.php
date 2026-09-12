<?php

declare(strict_types=1);

namespace App\Catalog\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class PriceListRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $name = '',
        public readonly bool $default_includes_vat = false,
    ) {
    }
}
