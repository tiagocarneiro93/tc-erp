<?php

declare(strict_types=1);

namespace App\Inventory\UI\Http;

use Symfony\Component\Validator\Constraints as Assert;

final class WarehouseRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $code = '',
        #[Assert\NotBlank]
        public readonly string $name = '',
        public readonly ?string $address = null,
        public readonly bool $is_default = false,
    ) {
    }
}
