<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets Fiscal validate a
 * draft's `customer_id` without depending on `Parties\Domain\Customer`,
 * which Deptrac forbids. Company-scoped, like {@see PaymentTermsExistenceChecker}.
 */
interface CustomerExistenceChecker
{
    public function exists(CompanyId $companyId, string $customerId): bool;
}
