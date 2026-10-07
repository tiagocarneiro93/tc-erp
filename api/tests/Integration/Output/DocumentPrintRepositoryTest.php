<?php

declare(strict_types=1);

namespace App\Tests\Integration\Output;

use App\Output\Domain\DocumentPrint;
use App\Output\Domain\DocumentPrintKind;
use App\Output\Domain\DocumentPrintRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\TransactionManager;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * `document_prints` against real PostgreSQL: insert-only, company-scoped, and
 * its CHECK constraint on `kind`.
 */
final class DocumentPrintRepositoryTest extends KernelTestCase
{
    private DocumentPrintRepository $prints;
    private TransactionManager $transactions;
    private RequestCompanyContext $companyContext;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var DocumentPrintRepository $prints */
        $prints = $container->get(DocumentPrintRepository::class);
        $this->prints = $prints;
        /** @var TransactionManager $transactions */
        $transactions = $container->get(TransactionManager::class);
        $this->transactions = $transactions;
        /** @var RequestCompanyContext $context */
        $context = $container->get(RequestCompanyContext::class);
        $this->companyContext = $context;
        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();
        parent::tearDown();
    }

    public function testEveryKindIsLoggedAndCounted(): void
    {
        $company = $this->asCompany();
        $documentId = Uuid::v7()->toRfc4122();

        $this->transactions->transactional(function () use ($company, $documentId): void {
            foreach (DocumentPrintKind::cases() as $kind) {
                $this->prints->add($this->print($company, $documentId, $kind));
            }
        });

        self::assertSame(3, $this->transactions->transactional(fn (): int => $this->prints->countFor($company, $documentId)));
        self::assertSame(0, $this->transactions->transactional(fn (): int => $this->prints->countFor($company, Uuid::v7()->toRfc4122())));
    }

    public function testACompanyNeverSeesAnotherCompanysPrints(): void
    {
        $owner = $this->asCompany();
        $documentId = Uuid::v7()->toRfc4122();
        $this->transactions->transactional(fn () => $this->prints->add($this->print($owner, $documentId, DocumentPrintKind::Download)));

        $other = $this->asCompany();

        self::assertSame(0, $this->transactions->transactional(fn (): int => $this->prints->countFor($other, $documentId)));
    }

    public function testRowsCannotBeUpdatedOrDeleted(): void
    {
        $company = $this->asCompany();
        $documentId = Uuid::v7()->toRfc4122();
        $print = $this->print($company, $documentId, DocumentPrintKind::Print);
        $this->transactions->transactional(fn () => $this->prints->add($print));

        $refused = 0;

        foreach (['UPDATE document_prints SET copy_label = \'Original\' WHERE id = ?', 'DELETE FROM document_prints WHERE id = ?'] as $statement) {
            try {
                $this->transactions->transactional(fn () => $this->connection->executeStatement($statement, [$print->id]));
            } catch (DbalException) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    public function testTheDatabaseRefusesAKindOutsideTheEnum(): void
    {
        $company = $this->asCompany();

        $this->expectException(DbalException::class);

        $this->transactions->transactional(fn () => $this->connection->insert('document_prints', [
            'id' => Uuid::v7()->toRfc4122(),
            'company_id' => $company->toString(),
            'document_id' => Uuid::v7()->toRfc4122(),
            'kind' => 'fax',
            'copy_label' => 'Original',
            'user_id' => 'someone',
            'occurred_at' => '2026-03-05 10:00:00+00',
        ]));
    }

    public function testLockingADocumentIsSafeToCallInsideATransaction(): void
    {
        $company = $this->asCompany();

        $this->transactions->transactional(function () use ($company): void {
            $this->prints->lockDocument($company, Uuid::v7()->toRfc4122());
            $this->prints->lockDocument($company, Uuid::v7()->toRfc4122());
        });

        $this->addToAssertionCount(1);
    }

    private function asCompany(): CompanyId
    {
        $company = CompanyId::generate();
        $this->companyContext->set($company);

        return $company;
    }

    private function print(CompanyId $company, string $documentId, DocumentPrintKind $kind): DocumentPrint
    {
        return new DocumentPrint(Uuid::v7()->toRfc4122(), $company, $documentId, $kind, 'Original', 'user-1', new \DateTimeImmutable('2026-03-05T10:00:00+00:00'));
    }
}
