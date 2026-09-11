<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Company\CompanyContext;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

/**
 * technical-scope.md §5.2/§5.3: at the start of every transaction on the
 * `default` connection, if a company is set, run
 * `SELECT set_config('app.company_id', ..., true)` — the `true` (is_local)
 * argument scopes it to the transaction, like `SET LOCAL`, so it can never
 * leak into another request through a pooled connection. Nothing runs when
 * no company is set: RLS policies read `current_setting('app.company_id')`
 * with no default, so unscoped queries against company tables fail closed.
 */
final class CompanyContextConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        \Doctrine\DBAL\Driver\Connection $connection,
        private readonly CompanyContext $companyContext,
    ) {
        parent::__construct($connection);
    }

    public function beginTransaction(): void
    {
        parent::beginTransaction();

        if ($this->companyContext->hasCompany()) {
            $this->query(\sprintf(
                "SELECT set_config('app.company_id', %s, true)",
                $this->quote($this->companyContext->companyId()->toString()),
            ));
        }
    }
}
