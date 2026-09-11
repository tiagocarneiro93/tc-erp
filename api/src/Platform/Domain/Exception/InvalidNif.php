<?php

declare(strict_types=1);

namespace App\Platform\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidNif extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This is not a valid NIF.');
    }

    /**
     * technical-scope.md §9.1's own example of a stable type code.
     */
    public function problemType(): string
    {
        return 'nif-invalid';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
