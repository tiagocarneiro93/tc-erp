<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Idempotency\IdempotencyKeyStore;
use App\Shared\Domain\Idempotency\StoredIdempotentResponse;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Raw DBAL, like {@see \App\Shared\Infrastructure\Audit\DoctrineAuditLogger}:
 * `idempotency_keys` is a lookup table, not a domain aggregate.
 */
final class DoctrineIdempotencyKeyStore implements IdempotencyKeyStore
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
        private readonly Clock $clock,
    ) {
    }

    public function find(CompanyId $companyId, string $key): ?StoredIdempotentResponse
    {
        // Called directly from a controller, before any command/query bus
        // dispatch (config/packages/messenger.yaml's doctrine_transaction
        // middleware doesn't run yet) — without its own transaction here,
        // CompanyContextConnection::beginTransaction() never fires and
        // `current_setting('app.company_id')` fails RLS with "unrecognized
        // configuration parameter" (the exact gap that middleware's own
        // comment documents for query.bus/command.bus reads).
        return $this->connection->transactional(function () use ($companyId, $key): ?StoredIdempotentResponse {
            $row = $this->connection->fetchAssociative(
                'SELECT request_hash, response_status, response_body FROM idempotency_keys WHERE company_id = :company_id AND idempotency_key = :key',
                ['company_id' => $companyId->toString(), 'key' => $key],
            );

            if (false === $row) {
                return null;
            }

            if (!\is_string($row['request_hash']) || !is_numeric($row['response_status']) || !\is_string($row['response_body'])) {
                throw new \UnexpectedValueException('Unexpected column type reading idempotency_keys.');
            }

            return new StoredIdempotentResponse(
                $row['request_hash'],
                (int) $row['response_status'],
                $row['response_body'],
            );
        });
    }

    public function store(CompanyId $companyId, string $key, string $requestHash, int $status, string $body): void
    {
        $this->connection->transactional(function () use ($companyId, $key, $requestHash, $status, $body): void {
            $this->connection->insert('idempotency_keys', [
                'id' => Uuid::v7()->toRfc4122(),
                'company_id' => $companyId->toString(),
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'response_status' => $status,
                'response_body' => $body,
                'created_at' => $this->clock->now()->format('Y-m-d H:i:sP'),
            ]);
        });
    }
}
