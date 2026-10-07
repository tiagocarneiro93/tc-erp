<?php

declare(strict_types=1);

namespace App\AtIntegration\Infrastructure\Persistence;

use App\AtIntegration\Domain\Outbox\AtCommunicationOutbox;
use App\AtIntegration\Domain\Outbox\ClaimedAtCommunication;
use App\AtIntegration\Domain\Outbox\DueAtCommunication;
use App\AtIntegration\Domain\Outbox\RetryPolicy;
use App\Shared\Domain\CompanyId;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Raw DBAL on the shared `default` connection, like Fiscal's own readers —
 * `at_communications` has no Doctrine entity. The company-scoped methods rely
 * on the surrounding {@see \App\Shared\Domain\TransactionManager} transaction
 * (RLS scope set at its start); {@see self::findDue()} is the one call that
 * deliberately has no company, going through the `SECURITY DEFINER` function
 * from `Version20261007090000`.
 */
final class DbalAtCommunicationOutbox implements AtCommunicationOutbox
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:sP';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
    ) {
    }

    public function claim(CompanyId $companyId, string $communicationId, \DateTimeImmutable $now): ?ClaimedAtCommunication
    {
        /** @var array{id: string, kind: string, subject_type: string, subject_id: string, attempts: int|string}|false $row */
        $row = $this->connection->fetchAssociative(
            "UPDATE at_communications
             SET status = 'sending', attempts = attempts + 1, next_attempt_at = ?, updated_at = ?
             WHERE company_id = ? AND id = ?
               AND status IN ('pending', 'failed', 'sending')
               AND next_attempt_at <= ?
               AND attempts < ?
             RETURNING id, kind, subject_type, subject_id, attempts",
            [
                RetryPolicy::leaseUntil($now)->format(self::TIMESTAMP_FORMAT),
                $now->format(self::TIMESTAMP_FORMAT),
                $companyId->toString(),
                $communicationId,
                $now->format(self::TIMESTAMP_FORMAT),
                RetryPolicy::MAX_AUTOMATIC_ATTEMPTS,
            ],
        );

        if (false === $row) {
            return null;
        }

        return new ClaimedAtCommunication($row['id'], $row['kind'], $row['subject_type'], $row['subject_id'], (int) $row['attempts']);
    }

    public function recordOutcome(
        CompanyId $companyId,
        string $communicationId,
        string $status,
        ?int $responseCode,
        string $responseMessage,
        ?string $requestDigest,
        ?\DateTimeImmutable $nextAttemptAt,
        \DateTimeImmutable $now,
    ): void {
        $this->connection->executeStatement(
            'UPDATE at_communications
             SET status = ?, response_code = ?, response_message = ?, request_digest = ?,
                 next_attempt_at = COALESCE(?, next_attempt_at), updated_at = ?
             WHERE company_id = ? AND id = ?',
            [
                $status,
                null === $responseCode ? null : (string) $responseCode,
                $responseMessage,
                $requestDigest,
                $nextAttemptAt?->format(self::TIMESTAMP_FORMAT),
                $now->format(self::TIMESTAMP_FORMAT),
                $companyId->toString(),
                $communicationId,
            ],
        );
    }

    public function findDue(\DateTimeImmutable $now, int $limit): array
    {
        /** @var list<array{company_id: string, id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT company_id, id FROM at_communications_due(?, ?, ?)',
            [$now->format(self::TIMESTAMP_FORMAT), $limit, RetryPolicy::MAX_AUTOMATIC_ATTEMPTS],
        );

        return array_map(
            static fn (array $row): DueAtCommunication => new DueAtCommunication(CompanyId::fromString($row['company_id']), $row['id']),
            $rows,
        );
    }

    public function requestRetry(CompanyId $companyId, string $subjectType, string $subjectId, \DateTimeImmutable $now): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn(
            "UPDATE at_communications
             SET status = 'pending', attempts = 0, next_attempt_at = ?, updated_at = ?
             WHERE company_id = ? AND subject_type = ? AND subject_id = ?
               AND kind IN ('invoice', 'document_status') AND status IN ('failed', 'rejected')
             RETURNING id",
            [
                $now->format(self::TIMESTAMP_FORMAT),
                $now->format(self::TIMESTAMP_FORMAT),
                $companyId->toString(),
                $subjectType,
                $subjectId,
            ],
        );

        return $ids;
    }
}
