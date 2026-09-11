<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Security;

use App\Platform\Application\Security\PermissionChecker;
use App\Shared\Domain\CompanyId;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class SymfonyPermissionChecker implements PermissionChecker
{
    public function __construct(private readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    public function isGranted(string $permission, CompanyId $companyId): bool
    {
        return $this->authorizationChecker->isGranted($permission, $companyId);
    }
}
