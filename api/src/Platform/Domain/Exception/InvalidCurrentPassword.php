<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidCurrentPassword extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('The current password is incorrect.');
    }

    public function problemType(): string
    {
        return 'invalid-current-password';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
