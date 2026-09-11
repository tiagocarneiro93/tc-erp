<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

final class StoredIdempotentResponse
{
    public function __construct(
        public readonly string $requestHash,
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
