<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentId;

final class CreateCreditNoteDraft
{
    public function __construct(
        public readonly DocumentId $originalDocumentId,
        public readonly ?string $reason,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
