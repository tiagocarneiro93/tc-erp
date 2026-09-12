<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\PriceList;
use App\Catalog\Domain\PriceListId;
use App\Catalog\Domain\PriceListRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrinePriceListRepository implements PriceListRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, PriceListId $id): ?PriceList
    {
        $priceList = $this->entityManager->find(PriceList::class, $id);

        return null !== $priceList && $priceList->companyId()->equals($companyId) ? $priceList : null;
    }

    public function findAll(CompanyId $companyId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PriceList::class, 'p')
            ->where('p.companyId = :companyId')
            ->orderBy('p.name', 'ASC')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getResult();
    }

    public function save(PriceList $priceList): void
    {
        $this->entityManager->persist($priceList);
        $this->entityManager->flush();
    }
}
