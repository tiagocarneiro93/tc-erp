<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Repository;

use App\Tax\Domain\TaxRate;
use App\Tax\Domain\TaxRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineTaxRateRepository implements TaxRateRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findAll(?string $region, ?\DateTimeImmutable $asOf): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(TaxRate::class, 't')
            ->orderBy('t.region', 'ASC')
            ->addOrderBy('t.code', 'ASC')
            ->addOrderBy('t.validFrom', 'ASC');

        if (null !== $region) {
            $qb->andWhere('t.region = :region')->setParameter('region', $region);
        }

        if (null !== $asOf) {
            $qb->andWhere('t.validFrom <= :asOf')
                ->andWhere('t.validTo IS NULL OR t.validTo >= :asOf')
                ->setParameter('asOf', $asOf);
        }

        return $qb->getQuery()->getResult();
    }

    public function findApplicable(string $region, string $code, \DateTimeImmutable $date): ?TaxRate
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(TaxRate::class, 't')
            ->where('t.region = :region')
            ->andWhere('t.code = :code')
            ->andWhere('t.validFrom <= :date')
            ->andWhere('t.validTo IS NULL OR t.validTo >= :date')
            ->setParameter('region', $region)
            ->setParameter('code', $code)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof TaxRate ? $result : null;
    }
}
