<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

use App\Shared\Domain\CompanyId;

/**
 * ADR 0004's cross-module port pattern: issuance (docs/plans/phase-2.md
 * task 2.6) needs a customer's data frozen into `documents.customer_snapshot`
 * (technical-scope.md §6.6) at the moment of issuance, so a later change to
 * the customer record never alters an already-issued document — Fiscal has
 * no way to read `Parties\Domain\Customer` directly (Deptrac).
 */
interface CustomerSnapshotProvider
{
    /**
     * @return array<string, mixed>|null null when no such customer exists
     */
    public function snapshot(CompanyId $companyId, string $customerId): ?array;
}
