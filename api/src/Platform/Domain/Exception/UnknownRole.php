<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class UnknownRole extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This is not a known role.');
    }

    public function problemType(): string
    {
        return 'unknown-role';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
