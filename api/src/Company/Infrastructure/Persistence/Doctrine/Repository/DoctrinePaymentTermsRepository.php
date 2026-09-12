<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Persistence\Doctrine\Repository;

use App\Company\Domain\PaymentTerms;
use App\Company\Domain\PaymentTermsId;
use App\Company\Domain\PaymentTermsRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\PaymentTermsExistenceChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrinePaymentTermsRepository implements PaymentTermsRepository, PaymentTermsExistenceChecker
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, PaymentTermsId $id): ?PaymentTerms
    {
        $paymentTerms = $this->entityManager->find(PaymentTerms::class, $id);

        return null !== $paymentTerms && $paymentTerms->companyId()->equals($companyId) ? $paymentTerms : null;
    }

    public function findDefault(CompanyId $companyId): ?PaymentTerms
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PaymentTerms::class, 'p')
            ->where('p.companyId = :companyId')
            ->andWhere('p.isDefault = true')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof PaymentTerms ? $result : null;
    }

    public function findAll(CompanyId $companyId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PaymentTerms::class, 'p')
            ->where('p.companyId = :companyId')
            ->orderBy('p.days', 'ASC')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getResult();
    }

    public function save(PaymentTerms $paymentTerms): void
    {
        $this->entityManager->persist($paymentTerms);
        $this->entityManager->flush();
    }

    public function exists(CompanyId $companyId, string $paymentTermsId): bool
    {
        try {
            $id = PaymentTermsId::fromString($paymentTermsId);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return null !== $this->find($companyId, $id);
    }
}
