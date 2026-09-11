<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use App\Platform\Application\Security\PermissionChecker;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Security\PermissionChecker as SharedPermissionChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Implements both Platform's own port and the Shared cross-module twin
 * (docs/decisions/0004) — one adapter, since both interfaces have the exact
 * same signature and only Platform's Infrastructure layer can reach
 * `AuthorizationCheckerInterface`.
 */
final class SymfonyPermissionChecker implements PermissionChecker, SharedPermissionChecker
{
    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function isGranted(string $permission, CompanyId $companyId): bool
    {
        return $this->authorizationChecker->isGranted($permission, $companyId);
    }
}
