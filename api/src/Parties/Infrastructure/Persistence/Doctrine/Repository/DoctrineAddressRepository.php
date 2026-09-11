<?php

declare(strict_types=1);

namespace App\Parties\Infrastructure\Persistence\Doctrine\Repository;

use App\Parties\Domain\Address;
use App\Parties\Domain\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineAddressRepository implements AddressRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Address $address): void
    {
        $this->entityManager->persist($address);
        $this->entityManager->flush();
    }
}
