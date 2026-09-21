<?php

declare(strict_types=1);

namespace App\Shared\Domain\Fiscal;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module port (docs/decisions/0004's pattern): lets Parties check
 * whether a customer has at least one issued document, without depending
 * on `Fiscal\Domain`, which Deptrac forbids. Despacho 8632/2014
 * §3.3.3–3.3.5, docs/plans/phase-2.md task 2.3: once true, `nif`/`name`
 * lock on that customer (with the two documented exceptions —
 * `UpdateCustomerHandler` decides those, this port only answers yes/no).
 */
interface CustomerHasIssuedDocuments
{
    public function forCustomer(CompanyId $companyId, string $customerId): bool;
}
