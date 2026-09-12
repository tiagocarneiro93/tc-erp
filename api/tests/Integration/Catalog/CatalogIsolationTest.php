<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\PriceList;
use App\Catalog\Domain\PriceListId;
use App\Catalog\Domain\PriceListRepository;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductFamily;
use App\Catalog\Domain\ProductFamilyId;
use App\Catalog\Domain\ProductFamilyRepository;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductPrice;
use App\Catalog\Domain\ProductPriceRepository;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * technical-scope.md §5.2/§5.3, docs/plans/phase-1.md tasks 1.6/1.7: proves
 * RLS isolates `products`, `product_families`, `price_lists` and
 * `product_prices` through the same repositories the application uses. See
 * `CompanyModuleIsolationTest` (task 1.4) for why the EntityManager's
 * identity map is cleared between company contexts.
 */
final class CatalogIsolationTest extends KernelTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private RequestCompanyContext $companyContext;
    private ProductRepository $products;
    private ProductFamilyRepository $families;
    private PriceListRepository $priceLists;
    private ProductPriceRepository $prices;

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
        /** @var ProductRepository $products */
        $products = self::getContainer()->get(ProductRepository::class);
        $this->products = $products;
        /** @var ProductFamilyRepository $families */
        $families = self::getContainer()->get(ProductFamilyRepository::class);
        $this->families = $families;
        /** @var PriceListRepository $priceLists */
        $priceLists = self::getContainer()->get(PriceListRepository::class);
        $this->priceLists = $priceLists;
        /** @var ProductPriceRepository $prices */
        $prices = self::getContainer()->get(ProductPriceRepository::class);
        $this->prices = $prices;
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();

        foreach ($this->companiesToCleanUp as $companyId) {
            $this->companyContext->set($companyId);
            $this->connection->beginTransaction();
            $this->connection->executeStatement('DELETE FROM product_prices WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->executeStatement('DELETE FROM price_lists WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->executeStatement('DELETE FROM products WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->executeStatement('DELETE FROM product_families WHERE company_id = :id', ['id' => $companyId->toString()]);
            $this->connection->commit();
            $this->companyContext->clear();
        }

        parent::tearDown();
    }

    public function testACompanyCannotReadAnotherCompanysProduct(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->products->save(Product::create(
            ProductId::generate(),
            $companyA,
            'A-PROD',
            'Company A product',
            'P',
            'simple',
            'UN',
            null,
            null,
            'a-tax-rate-id',
            null,
            false,
        ));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->products->search($companyB, null, null, null, null);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s product.');
    }

    public function testACompanyCannotReadAnotherCompanysProductFamily(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->families->save(ProductFamily::create(ProductFamilyId::generate(), $companyA, 'Company A family', null));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->families->findAll($companyB);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s product family.');
    }

    public function testACompanyCannotReadAnotherCompanysPriceList(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $this->priceLists->save(new PriceList(PriceListId::generate(), $companyA, 'Company A price list', false));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->priceLists->findAll($companyB);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s price list.');
    }

    public function testACompanyCannotReadAnotherCompanysProductPrice(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();

        $this->companyContext->set($companyA);
        $this->connection->beginTransaction();
        $productId = ProductId::generate();
        $priceListId = PriceListId::generate();
        $this->products->save(Product::create($productId, $companyA, 'A-PROD', 'Company A product', 'P', 'simple', 'UN', null, null, 'a-tax-rate-id', null, false));
        $this->priceLists->save(new PriceList($priceListId, $companyA, 'Company A price list', false));
        $this->prices->save(ProductPrice::set($companyA, $productId, $priceListId, '9.990000', true));
        $this->connection->commit();
        $this->companyContext->clear();

        $this->entityManager->clear();
        $this->companyContext->set($companyB);
        $this->connection->beginTransaction();
        $seenAsB = $this->prices->findAllForProduct($companyB, $productId);
        $this->connection->commit();
        $this->companyContext->clear();

        self::assertSame([], $seenAsB, 'Company B must not see company A\'s product price.');
    }

    private function newCompany(): CompanyId
    {
        $companyId = CompanyId::generate();
        $this->companiesToCleanUp[] = $companyId;

        return $companyId;
    }
}
