<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine\Repository;

use App\Fiscal\Domain\DocumentDraft;
use App\Fiscal\Domain\DocumentDraftId;
use App\Fiscal\Domain\DocumentDraftRepository;
use App\Shared\Domain\CompanyId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DoctrineDocumentDraftRepository implements DocumentDraftRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.default_entity_manager')]
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(CompanyId $companyId, DocumentDraftId $id): ?DocumentDraft
    {
        $draft = $this->entityManager->find(DocumentDraft::class, $id);

        return null !== $draft && $draft->companyId()->equals($companyId) ? $draft : null;
    }

    public function findAll(CompanyId $companyId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('d')
            ->from(DocumentDraft::class, 'd')
            ->where('d.companyId = :companyId')
            ->orderBy('d.updatedAt', 'DESC')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getResult();
    }

    public function save(DocumentDraft $draft): void
    {
        $this->entityManager->persist($draft);
        $this->entityManager->flush();
    }

    public function remove(DocumentDraft $draft): void
    {
        $this->entityManager->remove($draft);
        $this->entityManager->flush();
    }
}
