<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Command;

use App\Fiscal\Domain\DocumentId;

final class CreateConversionDraft
{
    public function __construct(
        public readonly DocumentId $sourceDocumentId,
        public readonly string $targetDocumentType,
        public readonly string $actingUserId,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }
}
