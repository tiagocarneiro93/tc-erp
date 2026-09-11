<?php

declare(strict_types=1);

namespace App\Platform\Domain;

/**
 * One (role_code, permission) grant; primary key is the pair, no surrogate id.
 */
final class RolePermission
{
    public function __construct(
        private readonly string $roleCode,
        private readonly string $permission,
    ) {
    }

    public function roleCode(): string
    {
        return $this->roleCode;
    }

    public function permission(): string
    {
        return $this->permission;
    }
}
