<?php

declare(strict_types=1);

namespace App\Company\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

final class AtCredentialsNotConfigured extends \DomainException implements ProblemDetails
{
    public function __construct()
    {
        parent::__construct('No AT credentials are configured for this company yet.');
    }

    public function problemType(): string
    {
        return 'at-credentials-not-configured';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
