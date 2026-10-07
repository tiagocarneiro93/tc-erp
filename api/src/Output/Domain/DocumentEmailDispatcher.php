<?php

declare(strict_types=1);

namespace App\Output\Domain;

use App\Shared\Domain\CompanyId;

/**
 * Queues the actual sending (seal, store, mail) for after the requesting
 * transaction has committed.
 */
interface DocumentEmailDispatcher
{
    /**
     * @param non-empty-list<string> $recipients
     */
    public function dispatchAfterCommit(CompanyId $companyId, string $documentId, array $recipients, ?string $message, string $actingUserId, string $ip, string $userAgent): void;
}
