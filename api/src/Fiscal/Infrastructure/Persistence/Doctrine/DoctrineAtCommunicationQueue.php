<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\AtCommunicationQueue;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final class DoctrineAtCommunicationQueue implements AtCommunicationQueue
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function enqueue(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, \DateTimeImmutable $now): void
    {
        $this->connection->insert('at_communications', [
            'id' => Uuid::v7()->toRfc4122(),
            'company_id' => $companyId->toString(),
            'kind' => $kind,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => $now->format('Y-m-d H:i:sP'),
            'created_at' => $now->format('Y-m-d H:i:sP'),
            'updated_at' => $now->format('Y-m-d H:i:sP'),
        ]);
    }
}
