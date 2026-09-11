<?php

declare(strict_types=1);

namespace App\Shared\Domain\Idempotency;

use App\Shared\Domain\Exception\ProblemDetails;

final class MissingIdempotencyKey extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('An Idempotency-Key header is required for this request.');
    }

    public function problemType(): string
    {
        return 'missing-idempotency-key';
    }

    public function httpStatus(): int
    {
        return 400;
    }
}
