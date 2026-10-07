<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Doctrine;

use App\Fiscal\Domain\AtCommunicationQueue;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Two connections, on purpose:
 *
 * - {@see self::enqueue()}/{@see self::enqueueStatusChange()} use the `default`
 *   connection: the outbox row must commit or roll back *with* the document
 *   it points at (technical-scope.md §7.5 — "written in the same transaction
 *   as the document → nothing is ever lost"). An independent connection here
 *   would leave an orphan `pending` row behind whenever issuance rolls back
 *   after the enqueue — a regression introduced alongside the audit-row fix
 *   below and caught in task 3.2.
 * - {@see self::recordResolved()} uses the `audit_log` connection — a
 *   genuinely separate physical connection, not just a separate transaction.
 *   `command.bus`'s `doctrine_transaction` middleware wraps a whole handler
 *   call in one transaction on `default`; a handler that calls
 *   `recordResolved()` and then deliberately throws to report an AT rejection
 *   as a 422 would, on `default`, have that throw roll back the very audit
 *   row meant to survive it — a real bug found live (2026-09-23) once
 *   `FakeSeriesWebserviceClient` (which never rejects) stopped being the only
 *   thing exercising this path. A savepoint doesn't fix this: it still
 *   unwinds when the outer transaction rolls back. Only a separate
 *   connection's own independent commit does.
 */
final class DoctrineAtCommunicationQueue implements AtCommunicationQueue
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
        #[Autowire(service: 'doctrine.dbal.audit_log_connection')]
        private readonly Connection $auditConnection,
    ) {
    }

    public function enqueue(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, \DateTimeImmutable $now): string
    {
        $id = Uuid::v7()->toRfc4122();

        $this->connection->insert('at_communications', [
            'id' => $id,
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

        return $id;
    }

    public function enqueueStatusChange(CompanyId $companyId, string $subjectId, \DateTimeImmutable $now): ?string
    {
        $id = Uuid::v7()->toRfc4122();

        $inserted = $this->connection->executeStatement(
            "INSERT INTO at_communications (id, company_id, kind, subject_type, subject_id, status, attempts, next_attempt_at, created_at, updated_at)
             SELECT ?, ?, 'document_status', 'Document', ?, 'pending', 0, ?, ?, ?
             WHERE EXISTS (
               SELECT 1 FROM at_communications
               WHERE company_id = ? AND kind = 'invoice' AND subject_type = 'Document' AND subject_id = ? AND status = 'accepted'
             )",
            [
                $id,
                $companyId->toString(),
                $subjectId,
                $now->format('Y-m-d H:i:sP'),
                $now->format('Y-m-d H:i:sP'),
                $now->format('Y-m-d H:i:sP'),
                $companyId->toString(),
                $subjectId,
            ],
        );

        return $inserted > 0 ? $id : null;
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
        $this->auditConnection->transactional(static function (Connection $connection) use ($companyId, $kind, $subjectType, $subjectId, $status, $responseCode, $responseMessage, $atReference, $now): void {
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
