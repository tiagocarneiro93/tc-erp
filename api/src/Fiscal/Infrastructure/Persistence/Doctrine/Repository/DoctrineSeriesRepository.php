<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine\Repository;

use App\Fiscal\Domain\Series;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\LockMode;
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

    /**
     * `find(..., LockMode::PESSIMISTIC_WRITE)` refreshes the entity when
     * it's already in the identity map (e.g. loaded moments earlier by
     * `ValidateDraftHandler`'s own read of the same series) — that refresh
     * path tries to reassign `Series`' `readonly` properties and fails.
     * `EntityManager::lock()` avoids that (it only issues the raw
     * `SELECT ... FOR UPDATE`, no rehydration) — but by the same token it
     * does *not* refresh the entity's field values either. Concretely:
     * this request may have `find()`d the series (and cached
     * `last_number`) *before* another concurrent issuance committed;
     * `lock()` then blocks until that other transaction's commit releases
     * the row, but the already-loaded `$series` object still holds the
     * pre-commit values once the lock is granted. Detaching and
     * re-`find()`ing — a fresh object, so no readonly-reassignment issue —
     * is what actually observes the now-current row under the lock. Without
     * this, two concurrent issuances can compute the same `nextNumber()`
     * and one loses the race only at the `documents` unique-constraint
     * insert, past the point of no return (signed, series already
     * updated) — {@see \App\Tests\Functional\Fiscal\IssuanceConcurrencyTest}
     * reproduces this exact failure mode without it.
     */
    public function findForUpdate(CompanyId $companyId, SeriesId $id): ?Series
    {
        $series = $this->find($companyId, $id);

        if (null === $series) {
            return null;
        }

        $this->entityManager->lock($series, LockMode::PESSIMISTIC_WRITE);
        $this->entityManager->detach($series);

        return $this->find($companyId, $id);
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
