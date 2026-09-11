<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Persistence\Doctrine\Repository;

use App\Platform\Domain\Company;
use App\Platform\Domain\CompanyId;
use App\Platform\Domain\CompanyRepository;
use App\Shared\Domain\Nif;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineCompanyRepository implements CompanyRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $id): ?Company
    {
        return $this->entityManager->find(Company::class, $id);
    }

    public function findByNif(Nif $nif): ?Company
    {
        return $this->entityManager->getRepository(Company::class)->findOneBy(['nif' => $nif]);
    }

    public function save(Company $company): void
    {
        $this->entityManager->persist($company);
        $this->entityManager->flush();
    }
}
