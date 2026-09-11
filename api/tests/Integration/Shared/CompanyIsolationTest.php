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
 * technical-scope.md §5.2/§5.3, CLAUDE.md task 0.9: proves isolation is
 * enforced by PostgreSQL itself, not just by application code — every
 * assertion here would still hold with the repository/query layer bypassed
 * entirely.
 *
 * Each scenario opens its own transaction (never nests them): the DBAL
 * middleware only reads the company context at `beginTransaction()`
 * (`SET LOCAL`-equivalent), so switching company mid-transaction would not
 * take effect — the same constraint production code has.
 */
final class CompanyIsolationTest extends KernelTestCase
{
    private const ACTION = 'isolation-test.probe';

    private Connection $connection;
    private RequestCompanyContext $companyContext;
    private AuditLogger $auditLogger;

    /** @var list<CompanyId> */
    private array $companiesToCleanUp = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
        /** @var RequestCompanyContext $context */
        $context = self::getContainer()->get(RequestCompanyContext::class);
        $this->companyContext = $context;
        /** @var AuditLogger $auditLogger */
        $auditLogger = self::getContainer()->get(AuditLogger::class);
        $this->auditLogger = $auditLogger;
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();

        // audit_log is insert-only for app_runtime (CLAUDE.md — issued
        // fiscal data is immutable, applied here as a dry run for the
        // pattern); only app_owner can clean up what this test inserted.
        /** @var Connection $migrations */
        $migrations = self::getContainer()->get('doctrine.dbal.migrations_connection');

        foreach ($this->companiesToCleanUp as $companyId) {
            $migrations->executeStatement("SELECT set_config('app.company_id', :id, false)", ['id' => $companyId->toString()]);
            $migrations->executeStatement(
                'DELETE FROM audit_log WHERE company_id = :id AND action = :action',
                ['id' => $companyId->toString(), 'action' => self::ACTION],
            );
        }

        parent::tearDown();
    }

    public function testACompanyCannotReadAnotherCompanysRows(): void
    {
        $companyA = $this->insertProbeRowForANewCompany('a-only');
        $companyB = $this->insertProbeRowForANewCompany('b-only');

        $seenByA = $this->readSubjectIdsAsCompany($companyA);
        $seenByB = $this->readSubjectIdsAsCompany($companyB);

        self::assertSame(['a-only'], $seenByA);
        self::assertSame(['b-only'], $seenByB);
    }

    public function testACompanyCannotInsertARowForAnotherCompany(): void
    {
        $companyA = CompanyId::generate();
        $companyB = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyA;
        $this->companiesToCleanUp[] = $companyB;

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();

        try {
            // The RLS policy's WITH CHECK rejects a row whose company_id
            // does not match the transaction's context, however it got
            // there — simulated here with a direct insert bypassing
            // AuditLogger, since AuditLogger always uses the context's id.
            $this->expectException(DbalException::class);
            $this->connection->insert('audit_log', [
                'id' => \Symfony\Component\Uid\Uuid::v7()->toRfc4122(),
                'company_id' => $companyB->toString(),
                'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:sP'),
                'user_id' => null,
                'api_token_id' => null,
                'action' => self::ACTION,
                'subject_type' => 'Probe',
                'subject_id' => 'cross-company-insert',
                'data' => '{}',
                'ip' => '127.0.0.1',
                'user_agent' => 'phpunit',
            ], ['data' => 'jsonb']);
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
    }

    public function testQueryingWithNoCompanyInContextFailsClosedEvenAfterAPriorTransactionSetAValue(): void
    {
        // A first, ordinary transaction on this connection does set a
        // value...
        $this->insertProbeRowForANewCompany('warm-up');

        // ...then a second, unrelated transaction with no context must
        // still fail closed: set_config's `true` (is_local) argument
        // really did scope the earlier value to its own transaction, with
        // nothing left over on the connection.
        $this->companyContext->clear();
        $this->connection->beginTransaction();

        try {
            $this->expectException(DbalException::class);
            $this->connection->fetchAllAssociative('SELECT 1 FROM audit_log');
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
        }
    }

    private function insertProbeRowForANewCompany(string $subjectId): CompanyId
    {
        $companyId = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyId;

        $this->companyContext->set($companyId);
        $this->connection->beginTransaction();
        $this->auditLogger->log(self::ACTION, 'Probe', $subjectId, [], null, null, '127.0.0.1', 'phpunit');
        $this->connection->commit();
        $this->companyContext->clear();

        return $companyId;
    }

    /**
     * @return list<string>
     */
    private function readSubjectIdsAsCompany(CompanyId $companyId): array
    {
        $this->companyContext->set($companyId);
        $this->connection->beginTransaction();
        $rows = $this->connection->fetchAllAssociative('SELECT subject_id FROM audit_log WHERE action = :action', ['action' => self::ACTION]);
        $this->connection->commit();
        $this->companyContext->clear();

        return array_map(static function (mixed $subjectId): string {
            self::assertIsString($subjectId);

            return $subjectId;
        }, array_column($rows, 'subject_id'));
    }
}
