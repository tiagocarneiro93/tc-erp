<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

final class KitComponentsView
{
    /**
     * @param list<ProductComponentView> $components
     */
    public function __construct(
        public readonly array $components,
        /**
         * Sum of component `average_cost` × quantity. Null whenever any
         * component's `average_cost` is null — always the case in Phase 1
         * (technical-scope.md task 1.8: "will read as zero/null until
         * Phase 5 populates average_cost, documented as a known gap, not
         * a bug").
         */
        public readonly ?string $estimatedCost,
    ) {
    }
}
