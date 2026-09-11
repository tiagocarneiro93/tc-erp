<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\ProductFamily;
use App\Catalog\Domain\ProductFamilyId;
use App\Catalog\Domain\ProductFamilyRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineProductFamilyRepository implements ProductFamilyRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, ProductFamilyId $id): ?ProductFamily
    {
        $family = $this->entityManager->find(ProductFamily::class, $id);

        return null !== $family && $family->companyId()->equals($companyId) ? $family : null;
    }

    public function findAll(CompanyId $companyId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(ProductFamily::class, 'f')
            ->where('f.companyId = :companyId')
            ->orderBy('f.name', 'ASC')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getResult();
    }

    public function save(ProductFamily $family): void
    {
        $this->entityManager->persist($family);
        $this->entityManager->flush();
    }
}
