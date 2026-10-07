<?php

declare(strict_types=1);

namespace App\Output\Application;

final class EmailDocument
{
    /**
     * @param list<string> $recipients empty: the customer's own address
     */
    public function __construct(
        public readonly string $documentId,
        public readonly array $recipients,
        public readonly ?string $message,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
