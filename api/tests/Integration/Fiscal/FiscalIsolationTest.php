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

/**
 * technical-scope.md §5.2/§5.3, docs/plans/phase-2.md task 2.2: proves RLS
 * isolates `series` through the same repository the application uses. See
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

    private function newCompany(): CompanyId
    {
        $companyId = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyId;

        return $companyId;
    }
}
