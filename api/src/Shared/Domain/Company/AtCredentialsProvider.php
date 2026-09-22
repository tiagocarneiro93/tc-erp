<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets `AtIntegration`
 * (docs/plans/phase-3.md task 3.1) get a company's AT sub-user credentials,
 * decrypted, without depending on `Company\Domain`, which Deptrac forbids.
 * Returns null rather than throwing when nothing is configured yet — same
 * shape as {@see \App\Shared\Domain\Tax\PriceCalculationService::calculate()}
 * — so the caller (which knows *why* it needed credentials) decides what
 * that means, instead of this port assuming everyone wants the same error.
 */
interface AtCredentialsProvider
{
    public function forCompany(CompanyId $companyId): ?DecryptedAtCredentials;
}
