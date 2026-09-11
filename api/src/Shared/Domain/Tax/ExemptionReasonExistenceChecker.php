<?php

declare(strict_types=1);

namespace App\Shared\Domain\Tax;

/**
 * See {@see TaxRateExistenceChecker}'s docblock — same reasoning, for
 * `exemption_reasons.code` (e.g. `products.exemption_reason_code`, task 1.6).
 */
interface ExemptionReasonExistenceChecker
{
    public function exists(string $code): bool;
}
