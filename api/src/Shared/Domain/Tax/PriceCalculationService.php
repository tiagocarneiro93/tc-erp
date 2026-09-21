<?php

declare(strict_types=1);

namespace App\Shared\Domain\Tax;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets Fiscal run the
 * exact same calculation `POST /companies/{c}/calculate` does (task 2.1),
 * without depending on `Tax\Domain\PriceCalculator`/`Tax\Application`,
 * which Deptrac forbids. docs/plans/phase-2.md decision 1: "Fiscal depends
 * on Tax's calculator the same way Catalog already depends on Tax's
 * rate-resolution port.".
 *
 * Both $payload and the return value use the same raw shape as
 * `POST /calculate`'s request/response body (technical-scope.md §7.9.6) —
 * plain arrays, not `Tax\Domain` value objects, for the same reason
 * {@see TaxRateConverter} returns a plain string rather than a `Money`.
 */
interface PriceCalculationService
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null null if $payload isn't calculable
     *                                   yet (missing/invalid fields) —
     *                                   never thrown, since a draft is
     *                                   mutable and allowed to be
     *                                   momentarily incomplete
     */
    public function calculate(array $payload): ?array;
}
