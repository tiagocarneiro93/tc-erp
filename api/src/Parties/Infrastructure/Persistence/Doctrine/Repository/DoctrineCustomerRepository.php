<?php

declare(strict_types=1);

namespace App\Parties\Infrastructure\Persistence\Doctrine\Repository;

use App\Parties\Domain\Customer;
use App\Parties\Domain\CustomerId;
use App\Parties\Domain\CustomerRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\CustomerExistenceChecker;
use App\Shared\Domain\Fiscal\CustomerSnapshotProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineCustomerRepository implements CustomerRepository, CustomerExistenceChecker, CustomerSnapshotProvider
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, CustomerId $id): ?Customer
    {
        $customer = $this->entityManager->find(Customer::class, $id);

        return null !== $customer && $customer->companyId()->equals($companyId) ? $customer : null;
    }

    public function exists(CompanyId $companyId, string $customerId): bool
    {
        try {
            $id = CustomerId::fromString($customerId);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return null !== $this->find($companyId, $id);
    }

    public function search(CompanyId $companyId, ?string $search): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->where('c.companyId = :companyId')
            ->orderBy('c.id', 'ASC')
            ->setParameter('companyId', $companyId);

        if (null !== $search && '' !== $search) {
            $qb->andWhere('c.code LIKE :search OR c.nif LIKE :search OR c.name LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function save(Customer $customer): void
    {
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
    }

    public function snapshot(CompanyId $companyId, string $customerId): ?array
    {
        try {
            $id = CustomerId::fromString($customerId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $customer = $this->find($companyId, $id);

        if (null === $customer) {
            return null;
        }

        return [
            'nif' => $customer->nif(),
            'name' => $customer->name(),
            'address' => $customer->address(),
            'postal_code' => $customer->postalCode(),
            'city' => $customer->city(),
            'country' => $customer->country(),
        ];
    }
}
