<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidCountryCode extends \DomainException implements ProblemDetails
{
    public function __construct(string $countryCode)
    {
        parent::__construct(\sprintf('"%s" is not a known country code.', $countryCode));
    }

    public function problemType(): string
    {
        return 'invalid-country-code';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
