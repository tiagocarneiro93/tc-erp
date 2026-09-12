<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Tax\TaxRateConverter;
use App\Shared\Domain\Tax\TaxRateExemptionChecker;
use App\Shared\Domain\Tax\TaxRateExistenceChecker;
use App\Tax\Domain\TaxRate;
use App\Tax\Domain\TaxRateId;
use App\Tax\Domain\TaxRateRepository;
use App\Tax\Domain\VatConversion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineTaxRateRepository implements TaxRateRepository, TaxRateExistenceChecker, TaxRateConverter, TaxRateExemptionChecker
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

    public function exists(string $taxRateId): bool
    {
        return null !== $this->findById($taxRateId);
    }

    public function isExempt(string $taxRateId): bool
    {
        return 'ISE' === $this->findById($taxRateId)?->code();
    }

    public function convertToOtherMode(string $taxRateId, string $amount, bool $includesVat): ?string
    {
        $taxRate = $this->findById($taxRateId);

        if (null === $taxRate) {
            return null;
        }

        $decimalAmount = Decimal::fromString($amount);
        $converted = $includesVat
            ? VatConversion::toNet($decimalAmount, $taxRate->percentage())
            : VatConversion::toGross($decimalAmount, $taxRate->percentage());

        return $converted->toString();
    }

    private function findById(string $taxRateId): ?TaxRate
    {
        try {
            $id = TaxRateId::fromString($taxRateId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $taxRate = $this->entityManager->find(TaxRate::class, $id);

        return $taxRate instanceof TaxRate ? $taxRate : null;
    }
}
