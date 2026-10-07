<?php

declare(strict_types=1);

namespace App\Output\Application;

/**
 * The queued half of an e-mail request. Plain scalars, so it serialises
 * trivially onto the Redis transport; the company travels as a `CompanyStamp`.
 */
final class SendDocumentEmail
{
    /**
     * @param non-empty-list<string> $recipients
     */
    public function __construct(
        public readonly string $companyId,
        public readonly string $documentId,
        public readonly array $recipients,
        public readonly ?string $message,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
