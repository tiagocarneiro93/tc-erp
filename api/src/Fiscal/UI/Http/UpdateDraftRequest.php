<?php

declare(strict_types=1);

namespace App\Fiscal\UI\Http;

/**
 * See {@see CreateDraftRequest}'s docblock — same reasoning. No
 * `document_type` here: it's fixed at creation.
 */
final class UpdateDraftRequest
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly array $payload = [],
    ) {
    }
}
