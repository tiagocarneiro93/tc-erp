<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentDraftId;

final class IssueDraft
{
    public function __construct(
        public readonly DocumentDraftId $draftId,
        public readonly string $idempotencyKey,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
