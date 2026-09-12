<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\ProductComponent;
use App\Catalog\Domain\ProductComponentRepository;
use App\Catalog\Domain\ProductId;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineProductComponentRepository implements ProductComponentRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, ProductId $kitProductId, ProductId $componentProductId): ?ProductComponent
    {
        return $this->entityManager->find(ProductComponent::class, [
            'companyId' => $companyId,
            'kitProductId' => $kitProductId,
            'componentProductId' => $componentProductId,
        ]);
    }

    public function findAllForKit(CompanyId $companyId, ProductId $kitProductId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(ProductComponent::class, 'c')
            ->where('c.companyId = :companyId')
            ->andWhere('c.kitProductId = :kitProductId')
            ->orderBy('c.sortOrder', 'ASC')
            ->setParameter('companyId', $companyId)
            ->setParameter('kitProductId', $kitProductId)
            ->getQuery()
            ->getResult();
    }

    public function save(ProductComponent $component): void
    {
        $this->entityManager->persist($component);
        $this->entityManager->flush();
    }

    public function remove(ProductComponent $component): void
    {
        $this->entityManager->remove($component);
        $this->entityManager->flush();
    }
}
