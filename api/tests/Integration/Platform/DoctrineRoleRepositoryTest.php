<?php

declare(strict_types=1);

namespace App\Tests\Integration\Platform;

use App\Platform\Domain\RoleRepository;

/**
 * Exercises the roles/role_permissions seeded by the migration
 * (docs/decisions/0002-roles-and-permissions.md) — read-only, no writes,
 * so it doesn't need the transaction-rollback base class, but reuses it
 * for a consistent bootKernel().
 */
final class DoctrineRoleRepositoryTest extends PlatformRepositoryTestCase
{
    public function testAllSixRolesAreSeeded(): void
    {
        /** @var RoleRepository $repository */
        $repository = self::getContainer()->get(RoleRepository::class);

        $codes = array_map(static fn ($role) => $role->code(), $repository->findAll());
        sort($codes);

        self::assertSame(['accountant', 'admin', 'billing', 'owner', 'read_only', 'stock'], $codes);
    }

    public function testOwnerHasEveryPermissionIncludingCompanyDelete(): void
    {
        /** @var RoleRepository $repository */
        $repository = self::getContainer()->get(RoleRepository::class);

        self::assertContains('company.delete', $repository->permissionsFor('owner'));
        self::assertCount(17, $repository->permissionsFor('owner'));
    }

    public function testAdminHasEveryPermissionExceptCompanyDelete(): void
    {
        /** @var RoleRepository $repository */
        $repository = self::getContainer()->get(RoleRepository::class);

        $permissions = $repository->permissionsFor('admin');

        self::assertNotContains('company.delete', $permissions);
        self::assertCount(16, $permissions);
    }

    public function testReadOnlyHasNoManagePermissions(): void
    {
        /** @var RoleRepository $repository */
        $repository = self::getContainer()->get(RoleRepository::class);

        foreach ($repository->permissionsFor('read_only') as $permission) {
            self::assertStringEndsWith('.read', $permission);
        }
    }
}
