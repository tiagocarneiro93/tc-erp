<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentDraftId;

final class UpdateDraft
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly DocumentDraftId $draftId,
        public readonly string $actingUserId,
        public readonly array $payload,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
