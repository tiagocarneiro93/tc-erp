<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\AtCommunicationQueue;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Uses the `audit_log` connection, not `default` — a genuinely separate
 * physical connection, not just a separate transaction. `command.bus`'s
 * `doctrine_transaction` middleware wraps a whole handler call in one
 * transaction on `default`; a handler that calls `recordResolved()` and
 * then deliberately throws to report an AT rejection as a 422 would, on
 * `default`, have that throw roll back the very audit row meant to survive
 * it — a real bug found live (2026-09-23) once `FakeSeriesWebserviceClient`
 * (which never rejects) stopped being the only thing exercising this path.
 * A savepoint doesn't fix this: it still unwinds when the outer transaction
 * rolls back. Only a separate connection's own independent commit does.
 */
final class DoctrineAtCommunicationQueue implements AtCommunicationQueue
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.audit_log_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function enqueue(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, \DateTimeImmutable $now): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($companyId, $kind, $subjectType, $subjectId, $now): void {
            $connection->insert('at_communications', [
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
        });
    }

    public function recordResolved(
        CompanyId $companyId,
        string $kind,
        string $subjectType,
        string $subjectId,
        string $status,
        int $responseCode,
        string $responseMessage,
        ?string $atReference,
        \DateTimeImmutable $now,
    ): void {
        $this->connection->transactional(static function (Connection $connection) use ($companyId, $kind, $subjectType, $subjectId, $status, $responseCode, $responseMessage, $atReference, $now): void {
            $connection->insert('at_communications', [
                'id' => Uuid::v7()->toRfc4122(),
                'company_id' => $companyId->toString(),
                'kind' => $kind,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'status' => $status,
                'attempts' => 1,
                'next_attempt_at' => $now->format('Y-m-d H:i:sP'),
                'response_code' => (string) $responseCode,
                'response_message' => $responseMessage,
                'at_reference' => $atReference,
                'created_at' => $now->format('Y-m-d H:i:sP'),
                'updated_at' => $now->format('Y-m-d H:i:sP'),
            ]);
        });
    }
}
