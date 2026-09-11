<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Never expected in normal operation — `CreateDefaultCompanyProfileOnCompanyRegistered`
 * inserts one for every company as soon as it's created. A 500, not a
 * client error: it would mean that side effect never ran.
 */
final class CompanyProfileNotFound extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('This company has no profile row.');
    }

    public function problemType(): string
    {
        return 'company-profile-not-found';
    }

    public function httpStatus(): int
    {
        return 500;
    }
}
