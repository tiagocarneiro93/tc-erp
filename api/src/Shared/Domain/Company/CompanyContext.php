<?php

declare(strict_types=1);

namespace App\Shared\Domain\Company;

use App\Shared\Domain\CompanyId;

/**
 * The company a unit of work (a request, a worker message) is acting as
 * (technical-scope.md §5.3). Domain/Application code depends on this port,
 * never on how the company was resolved or how it reaches the database
 * (CLAUDE.md — architecture rules).
 */
interface CompanyContext
{
    public function hasCompany(): bool;

    /**
     * @throws \LogicException if no company is set (fail closed — §5.2)
     */
    public function companyId(): CompanyId;
}
