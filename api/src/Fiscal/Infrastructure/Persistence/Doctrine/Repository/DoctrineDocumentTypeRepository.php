<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine\Repository;

use App\Fiscal\Domain\DocumentType;
use App\Fiscal\Domain\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineDocumentTypeRepository implements DocumentTypeRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(DocumentType::class)->findBy([], ['code' => 'ASC']);
    }

    public function find(string $code): ?DocumentType
    {
        return $this->entityManager->find(DocumentType::class, $code);
    }
}
