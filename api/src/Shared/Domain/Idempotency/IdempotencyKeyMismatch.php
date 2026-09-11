<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

use App\Shared\Domain\Exception\ProblemDetails;

final class IdempotencyKeyMismatch extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This Idempotency-Key was already used with a different request payload.');
    }

    public function problemType(): string
    {
        return 'idempotency-key-conflict';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
