<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * An e-mail carrying one document: plain text, the (sealed) PDF attached.
 */
final class OutgoingDocumentEmail
{
    /**
     * @param non-empty-list<string> $recipients
     */
    public function __construct(
        public readonly array $recipients,
        public readonly string $subject,
        public readonly string $textBody,
        public readonly ?string $replyTo,
        public readonly string $attachmentFilename,
        public readonly string $attachmentBytes,
    ) {
    }
}
