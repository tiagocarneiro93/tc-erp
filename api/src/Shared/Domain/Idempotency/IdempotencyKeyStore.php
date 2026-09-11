<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

use App\Shared\Domain\CompanyId;

/**
 * Backs `Idempotency-Key` support (technical-scope.md §9.1: "required on
 * all issuing/communicating endpoints"), ready ahead of Phase 2's actual
 * issuing endpoints (CLAUDE.md task 0.11).
 */
interface IdempotencyKeyStore
{
    public function find(CompanyId $companyId, string $key): ?StoredIdempotentResponse;

    public function store(CompanyId $companyId, string $key, string $requestHash, int $status, string $body): void;
}
