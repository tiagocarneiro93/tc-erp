<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class InvalidSaftPeriod extends \DomainException implements ProblemDetails
{
    public static function malformedDate(string $parameter): self
    {
        return new self(\sprintf('"%s" must be a date in the form YYYY-MM-DD.', $parameter));
    }

    public static function endBeforeStart(): self
    {
        return new self('The SAF-T period ends before it starts.');
    }

    public static function spansMoreThanOneYear(): self
    {
        return new self('A SAF-T file covers one fiscal year at most — split a period that crosses New Year into two files.');
    }

    public static function beforeTheSchemaAllows(): self
    {
        return new self('The SAF-T schema only allows fiscal years from 2000.');
    }

    public function problemType(): string
    {
        return 'invalid-saft-period';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
