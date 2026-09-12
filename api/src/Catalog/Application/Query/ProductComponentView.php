<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

final class ProductComponentView
{
    public function __construct(
        public readonly string $componentProductId,
        public readonly string $quantity,
        public readonly int $sortOrder,
        /**
         * Informational only (technical-scope.md §7.10.2: "the decision
         * stays with the user") — true when the component's tax rate
         * differs from the kit's own.
         */
        public readonly bool $vatRateMismatch,
    ) {
    }
}
