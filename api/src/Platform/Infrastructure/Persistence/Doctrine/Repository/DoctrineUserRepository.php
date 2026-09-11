<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Repository;

use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Explicitly the `default` (app_runtime) entity manager, not the
 * `migrations` (app_owner) one — the app never writes as app_owner
 * (CLAUDE.md — multi-tenancy).
 */
final class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
