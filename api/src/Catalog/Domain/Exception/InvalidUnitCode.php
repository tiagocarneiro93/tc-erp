<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidUnitCode extends \DomainException implements ProblemDetails
{
    public function __construct(string $unitCode)
    {
        parent::__construct(\sprintf('"%s" is not a known unit code.', $unitCode));
    }

    public function problemType(): string
    {
        return 'invalid-unit-code';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
