<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * DB integrity test (CLAUDE.md §13 test families): `audit_log` is
 * insert-only for `app_runtime` (technical-scope.md §6.12), enforced by an
 * explicit `REVOKE` in the migration, not merely by application code never
 * calling UPDATE/DELETE.
 */
final class AuditLogImmutabilityTest extends KernelTestCase
{
    public function testAppRuntimeCannotUpdateOrDeleteAuditLogRows(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        /** @var RequestCompanyContext $context */
        $context = self::getContainer()->get(RequestCompanyContext::class);
        /** @var AuditLogger $auditLogger */
        $auditLogger = self::getContainer()->get(AuditLogger::class);

        $companyId = CompanyId::generate();
        $context->set($companyId);
        $connection->beginTransaction();
        $auditLogger->log('immutability-test.probe', 'Probe', 'row-1', [], null, null, '127.0.0.1', 'phpunit');

        try {
            $this->expectException(DbalException::class);
            $this->expectExceptionMessageMatches('/permission denied/i');
            $connection->executeStatement("UPDATE audit_log SET action = 'tampered' WHERE subject_id = 'row-1'");
        } finally {
            $connection->rollBack();
            $context->clear();
        }
    }

    public function testAppRuntimeCannotDeleteAuditLogRows(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        /** @var RequestCompanyContext $context */
        $context = self::getContainer()->get(RequestCompanyContext::class);
        /** @var AuditLogger $auditLogger */
        $auditLogger = self::getContainer()->get(AuditLogger::class);

        $companyId = CompanyId::generate();
        $context->set($companyId);
        $connection->beginTransaction();
        $auditLogger->log('immutability-test.probe', 'Probe', 'row-2', [], null, null, '127.0.0.1', 'phpunit');

        try {
            $this->expectException(DbalException::class);
            $this->expectExceptionMessageMatches('/permission denied/i');
            $connection->executeStatement("DELETE FROM audit_log WHERE subject_id = 'row-2'");
        } finally {
            $connection->rollBack();
            $context->clear();
        }
    }
}
