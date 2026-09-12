<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\PriceListId;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductPrice;
use App\Catalog\Domain\ProductPriceRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineProductPriceRepository implements ProductPriceRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, ProductId $productId, PriceListId $priceListId): ?ProductPrice
    {
        return $this->entityManager->find(ProductPrice::class, [
            'companyId' => $companyId,
            'productId' => $productId,
            'priceListId' => $priceListId,
        ]);
    }

    public function findAllForProduct(CompanyId $companyId, ProductId $productId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(ProductPrice::class, 'p')
            ->where('p.companyId = :companyId')
            ->andWhere('p.productId = :productId')
            ->setParameter('companyId', $companyId)
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getResult();
    }

    public function save(ProductPrice $price): void
    {
        $this->entityManager->persist($price);
        $this->entityManager->flush();
    }
}
