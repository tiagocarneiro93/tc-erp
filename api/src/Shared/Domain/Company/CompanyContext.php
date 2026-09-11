<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

use App\Shared\Domain\CompanyId;

/**
 * The company a unit of work (a request, a worker message) is acting as
 * (technical-scope.md §5.3). Domain/Application code depends on this port,
 * never on how the company was resolved or how it reaches the database
 * (CLAUDE.md — architecture rules).
 *
 * `set()`/`clear()` are here, not only on the infrastructure implementation,
 * because a use case occasionally has to establish the context itself — a
 * new company's own id does not exist as a route parameter for anything to
 * resolve (`CreateCompanyHandler`), and it must already be set before the
 * command bus opens its transaction, or the audit entry written in the same
 * transaction would fail RLS's `WITH CHECK`.
 */
interface CompanyContext
{
    public function hasCompany(): bool;

    /**
     * @throws \LogicException if no company is set (fail closed — §5.2)
     */
    public function companyId(): CompanyId;

    public function set(CompanyId $companyId): void;

    public function clear(): void;
}
