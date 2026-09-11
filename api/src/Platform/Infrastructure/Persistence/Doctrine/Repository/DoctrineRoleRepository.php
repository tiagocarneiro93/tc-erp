<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Repository;

use App\Platform\Domain\Role;
use App\Platform\Domain\RolePermission;
use App\Platform\Domain\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineRoleRepository implements RoleRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(string $code): ?Role
    {
        return $this->entityManager->find(Role::class, $code);
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(Role::class)->findAll();
    }

    public function permissionsFor(string $roleCode): array
    {
        $permissions = $this->entityManager->getRepository(RolePermission::class)->findBy(['roleCode' => $roleCode]);

        return array_map(static fn (RolePermission $permission): string => $permission->permission(), $permissions);
    }
}
