<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Saft;

final class SaftProduct
{
    /**
     * @param string $type `ProductType`: P|S|O|E|I
     */
    public function __construct(
        public readonly string $type,
        public readonly string $code,
        public readonly string $description,
    ) {
    }
}
