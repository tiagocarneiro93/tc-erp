<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * CLAUDE.md §8.1: "password cannot be empty".
 */
final class EmptyPassword extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('Password cannot be empty.');
    }

    public function problemType(): string
    {
        return 'empty-password';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
