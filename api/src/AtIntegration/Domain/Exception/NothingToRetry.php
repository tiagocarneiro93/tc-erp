<?php

declare(strict_types=1);

namespace App\AtIntegration\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * A manual retry only makes sense for a communication that failed or was
 * rejected; one that is pending, in flight or accepted needs nothing.
 */
final class NothingToRetry extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This document has no failed or rejected AT communication to retry.');
    }

    public function problemType(): string
    {
        return 'at-communication-nothing-to-retry';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
