<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Repository;

use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\UserId;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineMembershipRepository implements MembershipRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(UserId $userId, CompanyId $companyId): ?Membership
    {
        return $this->entityManager->find(Membership::class, ['userId' => $userId, 'companyId' => $companyId]);
    }

    public function findByUser(UserId $userId): array
    {
        return $this->entityManager->getRepository(Membership::class)->findBy(['userId' => $userId]);
    }

    public function save(Membership $membership): void
    {
        $this->entityManager->persist($membership);
        $this->entityManager->flush();
    }
}
