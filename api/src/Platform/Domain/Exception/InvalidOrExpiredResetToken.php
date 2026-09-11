<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidOrExpiredResetToken extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This password reset link is invalid or has expired.');
    }

    public function problemType(): string
    {
        return 'invalid-or-expired-reset-token';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
