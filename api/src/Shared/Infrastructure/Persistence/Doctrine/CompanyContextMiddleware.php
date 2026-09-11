<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Company\CompanyContext;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Registered only for the `default` connection (config/packages/doctrine.yaml
 * / services.yaml) — the `migrations` connection runs DDL as app_owner and
 * never needs a company context.
 */
final class CompanyContextMiddleware implements Middleware
{
    public function __construct(private readonly CompanyContext $companyContext)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new CompanyContextDriver($driver, $this->companyContext);
    }
}
