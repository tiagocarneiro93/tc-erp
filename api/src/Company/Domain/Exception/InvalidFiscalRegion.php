<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidFiscalRegion extends \DomainException implements ProblemDetails
{
    public function __construct(string $fiscalRegion)
    {
        parent::__construct(\sprintf('"%s" is not a valid fiscal region.', $fiscalRegion));
    }

    public function problemType(): string
    {
        return 'invalid-fiscal-region';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
