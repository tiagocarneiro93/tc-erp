<?php

declare(strict_types=1);

namespace App\AtIntegration\Application\Command;

/**
 * technical-scope.md §7.5: `CommunicateToAt(companyId, communicationId)`.
 * Plain strings so the message serialises trivially onto the Redis transport;
 * the company also travels as a `CompanyStamp` so the worker restores its
 * RLS context before the handler runs (§5.4).
 */
final class CommunicateToAt
{
    public function __construct(
        public readonly string $companyId,
        public readonly string $communicationId,
    ) {
    }
}
