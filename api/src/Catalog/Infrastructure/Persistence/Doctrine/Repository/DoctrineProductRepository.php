<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductFamilyId;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\ProductSnapshotProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineProductRepository implements ProductRepository, ProductSnapshotProvider
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, ProductId $id): ?Product
    {
        $product = $this->entityManager->find(Product::class, $id);

        return null !== $product && $product->companyId()->equals($companyId) ? $product : null;
    }

    public function search(
        CompanyId $companyId,
        ?string $search,
        ?ProductFamilyId $familyId,
        ?bool $active,
        ?bool $trackStock,
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.companyId = :companyId')
            ->orderBy('p.id', 'ASC')
            ->setParameter('companyId', $companyId);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('p.code LIKE :search OR p.description LIKE :search OR p.barcode LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        if (null !== $familyId) {
            $qb->andWhere('p.familyId = :familyId')->setParameter('familyId', $familyId);
        }

        if (null !== $active) {
            $qb->andWhere('p.active = :active')->setParameter('active', $active);
        }

        if (null !== $trackStock) {
            $qb->andWhere('p.trackStock = :trackStock')->setParameter('trackStock', $trackStock);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function snapshot(CompanyId $companyId, string $productId): ?array
    {
        try {
            $id = ProductId::fromString($productId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $product = $this->find($companyId, $id);

        if (null === $product) {
            return null;
        }

        return [
            'code' => $product->code(),
            'description' => $product->description(),
            'type' => $product->type(),
            'unit_code' => $product->unitCode(),
        ];
    }
}
