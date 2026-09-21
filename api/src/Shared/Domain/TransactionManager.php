<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Demarcates an atomic unit of work spanning several Domain-port calls —
 * first needed by Fiscal's issuance use case (docs/plans/phase-2.md task
 * 2.6: series lock, canonical calculation, signing and multiple inserts
 * must commit or roll back together). `Application` code may not depend on
 * Doctrine directly outside pragmatic/CRUD areas (CLAUDE.md — "apply SOLID
 * strictly in Fiscal, Tax, Inventory and Accounts"), so this port is the
 * seam: the closure calls other Domain ports only, and never has to know
 * how (or on what connection) the transaction is actually run.
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    public function transactional(\Closure $operation): mixed;
}
