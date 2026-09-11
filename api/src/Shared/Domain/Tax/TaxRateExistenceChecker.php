<?php

declare(strict_types=1);

namespace App\Shared\Domain\Tax;

/**
 * Cross-module port (docs/decisions/0004's pattern, extended beyond
 * permissions/actor-id to reference-data existence checks): lets a module
 * other than Tax validate a `tax_rate_id` foreign key — e.g. `products.tax_rate_id`
 * (task 1.6) — without depending on `Tax\Domain\TaxRateRepository`, which
 * Deptrac forbids. Tax remains the sole owner of `TaxRate` itself; this
 * only answers yes/no.
 */
interface TaxRateExistenceChecker
{
    public function exists(string $taxRateId): bool;
}
