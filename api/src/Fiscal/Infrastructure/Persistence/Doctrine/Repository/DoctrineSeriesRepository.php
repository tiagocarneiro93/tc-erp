<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine\Repository;

use App\Fiscal\Domain\Series;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineSeriesRepository implements SeriesRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, SeriesId $id): ?Series
    {
        $series = $this->entityManager->find(Series::class, $id);

        return null !== $series && $series->companyId()->equals($companyId) ? $series : null;
    }

    public function findByCode(CompanyId $companyId, string $documentType, string $code): ?Series
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Series::class, 's')
            ->where('s.companyId = :companyId')
            ->andWhere('s.documentType = :documentType')
            ->andWhere('s.code = :code')
            ->setParameter('companyId', $companyId)
            ->setParameter('documentType', $documentType)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Series ? $result : null;
    }

    public function findAll(CompanyId $companyId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Series::class, 's')
            ->where('s.companyId = :companyId')
            ->orderBy('s.documentType', 'ASC')
            ->addOrderBy('s.code', 'ASC')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getResult();
    }

    public function save(Series $series): void
    {
        $this->entityManager->persist($series);
        $this->entityManager->flush();
    }
}
