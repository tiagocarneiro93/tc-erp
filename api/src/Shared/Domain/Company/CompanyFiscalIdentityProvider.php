<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module port (docs/decisions/0004's pattern), implemented by Company.
 * Needs a company transaction (RLS) around the call, like any company-scoped read.
 */
interface CompanyFiscalIdentityProvider
{
    public function forCompany(CompanyId $companyId): ?CompanyFiscalIdentity;
}
