<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Inventory\Domain\Warehouse;
use App\Inventory\Domain\WarehouseId;
use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineWarehouseRepository implements WarehouseRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, WarehouseId $id): ?Warehouse
    {
        $warehouse = $this->entityManager->find(Warehouse::class, $id);

        return null !== $warehouse && $warehouse->companyId()->equals($companyId) ? $warehouse : null;
    }

    public function findDefault(CompanyId $companyId): ?Warehouse
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(Warehouse::class, 'w')
            ->where('w.companyId = :companyId')
            ->andWhere('w.isDefault = true')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Warehouse ? $result : null;
    }

    public function findAll(CompanyId $companyId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(Warehouse::class, 'w')
            ->where('w.companyId = :companyId')
            ->orderBy('w.code', 'ASC')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getResult();
    }

    public function save(Warehouse $warehouse): void
    {
        $this->entityManager->persist($warehouse);
        $this->entityManager->flush();
    }
}
