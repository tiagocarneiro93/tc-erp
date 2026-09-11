<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Doctrine\Repository;

use App\Company\Domain\AtCredentials;
use App\Company\Domain\AtCredentialsRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineAtCredentialsRepository implements AtCredentialsRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId): ?AtCredentials
    {
        return $this->entityManager->find(AtCredentials::class, $companyId);
    }

    public function save(AtCredentials $credentials): void
    {
        $this->entityManager->persist($credentials);
        $this->entityManager->flush();
    }
}
