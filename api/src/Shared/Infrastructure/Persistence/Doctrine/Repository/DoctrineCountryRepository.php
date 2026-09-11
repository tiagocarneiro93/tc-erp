<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\Country;
use App\Shared\Domain\CountryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineCountryRepository implements CountryRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(Country::class)->findBy([], ['name' => 'ASC']);
    }
}
