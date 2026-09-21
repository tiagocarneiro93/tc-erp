<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fiscal;

use App\Fiscal\Domain\Series;
use App\Fiscal\Domain\SeriesId;
use App\Fiscal\Domain\SeriesRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * technical-scope.md §5.2/§5.3, docs/plans/phase-2.md tasks 2.2/2.3: proves
 * RLS isolates `series` (through the application's own repository) and
 * `documents` (raw SQL — no entity exists yet, task 2.3's own scope). See
 * `CompanyModuleIsolationTest` (task 1.4) for why the EntityManager's
 * identity map is cleared between company contexts.
 */
final class FiscalIsolationTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RequestCompanyContext $companyContext;
    private SeriesRepository $series;

    /** @var list<CompanyId> */
    private array $companiesToCleanUp = [];

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->entityManager = $entityManager;
        /** @var RequestCompanyContext $context */
        $context = self::getContainer()->get(RequestCompanyContext::class);
        $this->companyContext = $context;
        /** @var SeriesRepository $series */
        $series = self::getContainer()->get(SeriesRepository::class);
        $this->series = $series;
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();

        foreach ($this->companiesToCleanUp as $companyId) {
            $this->companyContext->set($companyId);
            $this->connection->beginTransaction();
            $this->connection->executeStatement('DELETE FROM series WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->commit();
            $this->companyContext->clear();
        }

        parent::tearDown();
    }

    public function testACompanyCannotReadAnotherCompanysSeries(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->series->save(Series::create(SeriesId::generate(), $companyA, 'FT', '2026A', false, 1));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->series->findAll($companyB);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s series.');
    }

    public function testTwoCompaniesCanUseTheSameDocumentTypeAndCodeIndependently(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->series->save(Series::create(SeriesId::generate(), $companyA, 'FT', '2026A', false, 1));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $this->series->save(Series::create(SeriesId::generate(), $companyB, 'FT', '2026A', false, 1));
        $seenAsB = $this->series->findAll($companyB);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertCount(1, $seenAsB, 'The UNIQUE(company_id, document_type, code) constraint is per company, not global.');
    }

    public function testACompanyCannotReadAnotherCompanysDocuments(): void
    {
        // `documents` has DELETE revoked entirely (task 2.3 — it's fiscal
        // data), so a committed row here could never be cleaned up by
        // app_runtime afterwards. Everything below runs and rolls back
        // inside a single transaction instead, switching the RLS company
        // directly via set_config (the app's own beginTransaction-time
        // middleware only fires once per transaction, so switching
        // mid-transaction needs the same primitive it uses internally).
        $companyA = CompanyId::generate();
        $companyB = CompanyId::generate();

        $this->connection->beginTransaction();

        $this->connection->executeStatement("SELECT set_config('app.company_id', :id, true)", ['id' => $companyA->toString()]);
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO documents (
                  id, company_id, document_type, series_id, number, document_no, atcud,
                  issue_date, system_entry_at, customer_snapshot, issuer_snapshot,
                  template_version, pricing_mode, rounding_method, currency,
                  settlement_total, net_total, tax_total, gross_total,
                  hash, hash_control, qr_payload, is_training, status, status_at,
                  source_id, issued_via
                ) VALUES (
                  :id, :companyId, 'FT', :seriesId, 1, 'FT 2026A/1', 'ABC-1',
                  now(), now(), '{}', '{}',
                  'v1', 'net', 'per_line', 'EUR',
                  100.00, 100.00, 23.00, 123.00,
                  'somehash', 'v1', 'qrpayload', false, 'N', now(),
                  'user1', 'web'
                )
                SQL,
            ['id' => Uuid::v7()->toRfc4122(), 'companyId' => $companyA->toString(), 'seriesId' => Uuid::v7()->toRfc4122()],
        );

        $this->connection->executeStatement("SELECT set_config('app.company_id', :id, true)", ['id' => $companyB->toString()]);
        $seenAsB = $this->connection->fetchAllAssociative('SELECT id FROM documents WHERE company_id = :id', ['id' => $companyA->toString()]);
        $seenAsBUnfiltered = $this->connection->fetchAllAssociative('SELECT id FROM documents');

        $this->connection->rollBack();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s document, even when querying by A\'s own id.');
        self::assertSame([], $seenAsBUnfiltered, 'Company B must not see company A\'s document via an unfiltered query either — RLS, not just an app_runtime WHERE clause.');
    }

    private function newCompany(): CompanyId
    {
        $companyId = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyId;

        return $companyId;
    }
}
