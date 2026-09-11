<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class PermissionDenied extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('You do not have permission to do this.');
    }

    public function problemType(): string
    {
        return 'permission-denied';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
