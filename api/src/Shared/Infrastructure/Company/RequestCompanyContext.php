<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Company;

use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Plain mutable holder, one instance per request/worker message (Symfony's
 * default service scope). Only infrastructure that resolves or restores the
 * company — the route listener, the Messenger middleware — calls
 * {@see self::set()}/{@see self::clear()}; everything else depends on the
 * read-only {@see CompanyContext} port. Implements {@see ResetInterface} so
 * a long-running worker runtime (e.g. FrankenPHP worker mode) can never
 * carry one request's company into the next.
 */
final class RequestCompanyContext implements CompanyContext, ResetInterface
{
    private ?CompanyId $companyId = null;

    public function set(CompanyId $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function clear(): void
    {
        $this->companyId = null;
    }

    public function hasCompany(): bool
    {
        return null !== $this->companyId;
    }

    public function companyId(): CompanyId
    {
        if (null === $this->companyId) {
            throw new \LogicException('No company is set in the current context.');
        }

        return $this->companyId;
    }

    public function reset(): void
    {
        $this->clear();
    }
}
