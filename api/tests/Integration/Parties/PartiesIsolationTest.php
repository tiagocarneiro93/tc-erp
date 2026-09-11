<?php

declare(strict_types=1);

namespace App\Tests\Integration\Parties;

use App\Parties\Domain\Customer;
use App\Parties\Domain\CustomerId;
use App\Parties\Domain\CustomerRepository;
use App\Parties\Domain\Supplier;
use App\Parties\Domain\SupplierId;
use App\Parties\Domain\SupplierRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * technical-scope.md §5.2/§5.3, docs/plans/phase-1.md task 1.5: proves RLS
 * isolates `customers` and `suppliers` through the same repositories the
 * application uses. See `CompanyModuleIsolationTest` (task 1.4) for why the
 * EntityManager's identity map is cleared before each cross-company read.
 */
final class PartiesIsolationTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RequestCompanyContext $companyContext;
    private CustomerRepository $customers;
    private SupplierRepository $suppliers;

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
        /** @var CustomerRepository $customers */
        $customers = self::getContainer()->get(CustomerRepository::class);
        $this->customers = $customers;
        /** @var SupplierRepository $suppliers */
        $suppliers = self::getContainer()->get(SupplierRepository::class);
        $this->suppliers = $suppliers;
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();

        foreach ($this->companiesToCleanUp as $companyId) {
            $this->companyContext->set($companyId);
            $this->connection->beginTransaction();
            $this->connection->executeStatement('DELETE FROM customers WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->executeStatement('DELETE FROM suppliers WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->commit();
            $this->companyContext->clear();
        }

        parent::tearDown();
    }

    public function testACompanyCannotReadAnotherCompanysCustomer(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->customers->save(Customer::create(
            CustomerId::generate(),
            $companyA,
            'C-A',
            $this->uniqueNif(),
            'Company A Customer',
            null,
            null,
            null,
            'PT',
            null,
            null,
            null,
            false,
            new \DateTimeImmutable(),
        ));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->customers->search($companyB, null);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s customer.');
    }

    public function testACompanyCannotReadAnotherCompanysSupplier(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->suppliers->save(Supplier::create(
            SupplierId::generate(),
            $companyA,
            'F-A',
            $this->uniqueNif(),
            'Company A Supplier',
            null,
            null,
            null,
            'PT',
            null,
            null,
            null,
            new \DateTimeImmutable(),
        ));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->suppliers->search($companyB, null);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s supplier.');
    }

    private function newCompany(): CompanyId
    {
        $companyId = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyId;

        return $companyId;
    }

    private function uniqueNif(): string
    {
        do {
            $prefix = (string) random_int(10_000_000, 99_999_999);
            $sum = 0;
            for ($position = 0; $position < 8; ++$position) {
                $sum += (int) $prefix[$position] * (9 - $position);
            }
            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? 0 : 11 - $remainder;
            $nif = $prefix.$checkDigit;
        } while (!Nif::isValid($nif));

        return $nif;
    }
}
