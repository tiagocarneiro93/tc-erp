<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Repository;

use App\Tax\Domain\ExemptionReason;
use App\Tax\Domain\ExemptionReasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineExemptionReasonRepository implements ExemptionReasonRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findAll(?\DateTimeImmutable $asOf): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(ExemptionReason::class, 'e')
            ->orderBy('e.code', 'ASC');

        if (null !== $asOf) {
            $qb->andWhere('e.validFrom <= :asOf')
                ->andWhere('e.validTo IS NULL OR e.validTo >= :asOf')
                ->setParameter('asOf', $asOf);
        }

        return $qb->getQuery()->getResult();
    }

    public function find(string $code): ?ExemptionReason
    {
        return $this->entityManager->find(ExemptionReason::class, $code);
    }
}
