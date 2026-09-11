<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Doctrine\Repository;

use App\Company\Domain\CompanyProfile;
use App\Company\Domain\CompanyProfileRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineCompanyProfileRepository implements CompanyProfileRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId): ?CompanyProfile
    {
        return $this->entityManager->find(CompanyProfile::class, $companyId);
    }

    public function save(CompanyProfile $profile): void
    {
        $this->entityManager->persist($profile);
        $this->entityManager->flush();
    }
}
