<?php

declare(strict_types=1);

namespace App\Parties\Infrastructure\Persistence\Doctrine\Repository;

use App\Parties\Domain\Supplier;
use App\Parties\Domain\SupplierId;
use App\Parties\Domain\SupplierRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineSupplierRepository implements SupplierRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, SupplierId $id): ?Supplier
    {
        $supplier = $this->entityManager->find(Supplier::class, $id);

        return null !== $supplier && $supplier->companyId()->equals($companyId) ? $supplier : null;
    }

    public function search(CompanyId $companyId, ?string $search): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Supplier::class, 's')
            ->where('s.companyId = :companyId')
            ->orderBy('s.id', 'ASC')
            ->setParameter('companyId', $companyId);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('s.code LIKE :search OR s.nif LIKE :search OR s.name LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function save(Supplier $supplier): void
    {
        $this->entityManager->persist($supplier);
        $this->entityManager->flush();
    }
}
