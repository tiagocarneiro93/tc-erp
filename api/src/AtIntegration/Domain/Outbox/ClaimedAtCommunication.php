<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Outbox;

final class ClaimedAtCommunication
{
    public function __construct(
        public readonly string $id,
        public readonly string $kind,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly int $attempts,
    ) {
    }
}
