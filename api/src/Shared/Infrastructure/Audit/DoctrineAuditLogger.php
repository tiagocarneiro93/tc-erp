<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Audit;

use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Raw DBAL insert rather than a Doctrine entity: `audit_log` is an
 * insert-only log (technical-scope.md §6.12), not a domain aggregate with
 * behaviour, so mapping it through the ORM would add nothing.
 */
final class DoctrineAuditLogger implements AuditLogger
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
        private readonly CompanyContext $companyContext,
        private readonly Clock $clock,
    ) {
    }

    public function log(
        string $action,
        string $subjectType,
        string $subjectId,
        array $data,
        ?string $userId,
        ?string $apiTokenId,
        string $ip,
        string $userAgent,
    ): void {
        $this->connection->insert('audit_log', [
            'id' => Uuid::v7()->toRfc4122(),
            'company_id' => $this->companyContext->companyId()->toString(),
            'occurred_at' => $this->clock->now()->format('Y-m-d H:i:sP'),
            'user_id' => $userId,
            'api_token_id' => $apiTokenId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'data' => json_encode($data, \JSON_THROW_ON_ERROR),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ], [
            'data' => 'jsonb',
        ]);
    }
}
