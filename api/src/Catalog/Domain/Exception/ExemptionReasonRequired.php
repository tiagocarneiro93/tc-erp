<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * technical-scope.md §6.5: `ISE` is the exempt SAF-T tax code; AT practice
 * requires a motivo de isenção on every exempt line, so a product using the
 * exempt rate must carry one too.
 */
final class ExemptionReasonRequired extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('An exemption reason is required when the tax rate is exempt (ISE).');
    }

    public function problemType(): string
    {
        return 'exemption-reason-required';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
