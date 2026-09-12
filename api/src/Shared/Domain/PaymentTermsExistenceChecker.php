<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets Parties validate
 * a `payment_terms_id` foreign key on a customer/supplier without
 * depending on `Company\Domain\PaymentTerms` directly, which Deptrac
 * forbids. Company-scoped, unlike the global Tax existence checkers, since
 * payment terms are a per-company catalog (task 1.5 follow-up).
 */
interface PaymentTermsExistenceChecker
{
    public function exists(CompanyId $companyId, string $paymentTermsId): bool;
}
