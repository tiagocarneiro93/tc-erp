<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class CompanyAlreadyExists extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('A company with this NIF already exists.');
    }

    public function problemType(): string
    {
        return 'company-already-exists';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
