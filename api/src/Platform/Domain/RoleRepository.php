<?php

declare(strict_types=1);

namespace App\Platform\Domain;

interface RoleRepository
{
    public function find(string $code): ?Role;

    /**
     * @return list<Role>
     */
    public function findAll(): array;

    /**
     * @return list<string> permission codes granted to this role
     */
    public function permissionsFor(string $roleCode): array;
}
