<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

/**
 * Runs a read against company-scoped tables inside an explicit transaction
 * (technical-scope.md §5.2/§5.3): RLS's `set_config(..., true)` behaves like
 * `SET LOCAL`, so it only survives for the lifetime of a transaction — a
 * bare `SELECT` outside one would lose the company context after a single
 * statement. Command handlers get the same guarantee for free from the
 * command.bus's `doctrine_transaction` middleware; query handlers that touch
 * company-scoped tables must wrap their read with this instead.
 */
interface CompanyQueryRunner
{
    /**
     * @template T
     *
     * @param \Closure(): T $query
     *
     * @return T
     */
    public function run(\Closure $query): mixed;
}
