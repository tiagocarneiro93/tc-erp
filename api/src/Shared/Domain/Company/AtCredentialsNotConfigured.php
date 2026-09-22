<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Lives in Shared, not Company\Domain, alongside {@see AtCredentialsProvider}:
 * every AT-integration adapter (any module) needs to react the same way
 * when a company hasn't configured AT credentials yet — this is a normal,
 * user-actionable business state, not a server misconfiguration, so it
 * gets a proper {@see ProblemDetails} mapping rather than falling through
 * to a generic 500 (technical-scope.md §9.1, RFC 9457).
 */
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
