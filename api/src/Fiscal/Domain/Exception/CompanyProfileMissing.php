<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class CompanyProfileMissing extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('Complete the company\'s fiscal profile (Settings) before exporting a SAF-T file.');
    }

    public function problemType(): string
    {
        return 'company-profile-missing';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
