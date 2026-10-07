<?php

declare(strict_types=1);

namespace App\AtIntegration\Application\Command;

final class RetryAtCommunication
{
    public function __construct(
        public readonly string $documentId,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
