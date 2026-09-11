<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidTaxRateId extends \DomainException implements ProblemDetails
{
    public function __construct(string $taxRateId)
    {
        parent::__construct(\sprintf('"%s" is not a known tax rate id.', $taxRateId));
    }

    public function problemType(): string
    {
        return 'invalid-tax-rate-id';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
