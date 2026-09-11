<?php

declare(strict_types=1);

namespace App\Shared\Domain\Security;

use App\Shared\Domain\CompanyId;

/**
 * Cross-module twin of `Platform\Application\Security\PermissionChecker`
 * (docs/decisions/0004) — every module other than Platform depends on this
 * one, since Deptrac forbids depending on another module's Application
 * layer. Same signature, one shared implementation
 * (`Platform\Infrastructure\Security\SymfonyPermissionChecker` implements
 * both interfaces).
 */
interface PermissionChecker
{
    public function isGranted(string $permission, CompanyId $companyId): bool;
}
