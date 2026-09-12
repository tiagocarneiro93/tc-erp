<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inventory;

use App\Inventory\Domain\Warehouse;
use App\Inventory\Domain\WarehouseId;
use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * technical-scope.md §5.2/§5.3, docs/plans/phase-1.md task 1.9: proves RLS
 * isolates `warehouses` through the same repository the application uses.
 * See `CompanyModuleIsolationTest` (task 1.4) for why the EntityManager's
 * identity map is cleared between company contexts.
 */
final class InventoryIsolationTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RequestCompanyContext $companyContext;
    private WarehouseRepository $warehouses;

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
        /** @var WarehouseRepository $warehouses */
        $warehouses = self::getContainer()->get(WarehouseRepository::class);
        $this->warehouses = $warehouses;
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();

        foreach ($this->companiesToCleanUp as $companyId) {
            $this->companyContext->set($companyId);
            $this->connection->beginTransaction();
            $this->connection->executeStatement('DELETE FROM warehouses WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->commit();
            $this->companyContext->clear();
        }

        parent::tearDown();
    }

    public function testACompanyCannotReadAnotherCompanysWarehouse(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->warehouses->save(Warehouse::create(WarehouseId::generate(), $companyA, 'A-ARM', 'Company A warehouse', null, true));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->warehouses->findAll($companyB);
        $seenDefaultAsB = $this->warehouses->findDefault($companyB);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s warehouse.');
        self::assertNull($seenDefaultAsB, 'Company B must not see company A\'s default warehouse either.');
    }

    private function newCompany(): CompanyId
    {
        $companyId = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyId;

        return $companyId;
    }
}
