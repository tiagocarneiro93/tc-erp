<?php

declare(strict_types=1);

namespace App\Platform\Application\Security;

use App\Shared\Domain\CompanyId;

/**
 * Lets a command/query handler enforce a specific permission (e.g.
 * `members.manage`) without depending on the Infrastructure Security
 * adapter directly — same reasoning as {@see CurrentUserId} (Deptrac:
 * Application may depend on Domain and Shared.Domain only).
 */
interface PermissionChecker
{
    public function isGranted(string $permission, CompanyId $companyId): bool;
}
