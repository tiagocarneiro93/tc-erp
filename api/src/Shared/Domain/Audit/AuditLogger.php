<?php

declare(strict_types=1);

namespace App\Shared\Domain\Audit;

/**
 * Writes an insert-only row to `audit_log` (technical-scope.md §6.12) in the
 * current {@see \App\Shared\Domain\Company\CompanyContext}. `userId` and
 * `apiTokenId` are plain strings, not `Platform\Domain` value objects: the
 * audit log is Shared infrastructure used by every module, and modules never
 * depend on another module's Domain (CLAUDE.md — architecture rules).
 */
interface AuditLogger
{
    /**
     * @param array<string, mixed> $data
     */
    public function log(
        string $action,
        string $subjectType,
        string $subjectId,
        array $data,
        ?string $userId,
        ?string $apiTokenId,
        string $ip,
        string $userAgent,
    ): void;
}
