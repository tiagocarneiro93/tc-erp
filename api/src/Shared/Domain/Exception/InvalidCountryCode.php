<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Validates a value against the `countries` table (task 1.1). First
 * introduced in `Company\Domain\Exception\InvalidCountryCode` (task 1.4);
 * promoted to Shared (docs/decisions/0004's pattern) once Parties (task
 * 1.5) needed the exact same check for `customers`/`suppliers.country`.
 */
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
