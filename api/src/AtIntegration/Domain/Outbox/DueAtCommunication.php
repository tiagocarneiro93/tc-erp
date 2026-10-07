<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Outbox;

use App\Shared\Domain\CompanyId;

/**
 * One `(company_id, item_id)` pair from the `SECURITY DEFINER` sweeper
 * function (technical-scope.md §5.4) — nothing else about the row.
 */
final class DueAtCommunication
{
    public function __construct(
        public readonly CompanyId $companyId,
        public readonly string $communicationId,
    ) {
    }
}
