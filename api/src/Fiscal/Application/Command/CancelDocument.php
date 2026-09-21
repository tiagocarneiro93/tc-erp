<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentId;

final class CancelDocument
{
    public function __construct(
        public readonly DocumentId $documentId,
        public readonly ?string $reason,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
