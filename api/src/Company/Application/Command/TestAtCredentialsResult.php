<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

final class TestAtCredentialsResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $checkedAt,
        public readonly ?string $error,
    ) {
    }
}
