<?php

declare(strict_types=1);

namespace App\Tests\Integration\AtIntegration;

use App\AtIntegration\Domain\Outbox\AtCommunicationOutbox;
use App\Fiscal\Domain\AtCommunicationQueue;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\TransactionManager;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The two database guarantees the AT outbox rests on (technical-scope.md
 * §5.4, §7.5), against real PostgreSQL with the real RLS policies.
 */
final class AtOutboxTest extends KernelTestCase
{
    private Connection $connection;
    private RequestCompanyContext $companyContext;
    private AtCommunicationQueue $queue;
    private AtCommunicationOutbox $outbox;
    private TransactionManager $transactions;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var Connection $connection */
        $connection = $container->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
        /** @var RequestCompanyContext $context */
        $context = $container->get(RequestCompanyContext::class);
        $this->companyContext = $context;
        /** @var AtCommunicationQueue $queue */
        $queue = $container->get(AtCommunicationQueue::class);
        $this->queue = $queue;
        /** @var AtCommunicationOutbox $outbox */
        $outbox = $container->get(AtCommunicationOutbox::class);
        $this->outbox = $outbox;
        /** @var TransactionManager $transactions */
        $transactions = $container->get(TransactionManager::class);
        $this->transactions = $transactions;

        $this->truncate();
    }

    protected function tearDown(): void
    {
        $this->companyContext->clear();
        $this->truncate();
        parent::tearDown();
    }

    public function testARolledBackIssuanceLeavesNoOrphanOutboxRow(): void
    {
        $companyId = CompanyId::generate();
        $this->companyContext->set($companyId);

        try {
            $this->transactions->transactional(function () use ($companyId): void {
                $this->queue->enqueue($companyId, 'invoice', 'Document', Uuid::v7()->toRfc4122(), new \DateTimeImmutable());

                throw new \RuntimeException('issuance failed after the enqueue');
            });
        } catch (\RuntimeException) {
            // expected: the transaction rolled back
        }

        self::assertSame(0, $this->countRows(), 'The outbox row must roll back together with the document it points at.');
    }

    public function testACommittedIssuanceKeepsItsOutboxRow(): void
    {
        $companyId = CompanyId::generate();
        $this->companyContext->set($companyId);

        $id = $this->transactions->transactional(fn (): string => $this->queue->enqueue($companyId, 'invoice', 'Document', Uuid::v7()->toRfc4122(), new \DateTimeImmutable()));

        self::assertSame(1, $this->countRows());
        self::assertTrue(Uuid::isValid($id));
    }

    public function testTheSweeperSeesDueWorkOfEveryCompanyButTheRuntimeRoleOnlyEverSeesItsOwn(): void
    {
        $now = new \DateTimeImmutable();
        $companyA = CompanyId::generate();
        $companyB = CompanyId::generate();
        $ids = [];

        foreach ([$companyA, $companyB] as $company) {
            $this->companyContext->set($company);
            $ids[$company->toString()] = $this->transactions->transactional(fn (): string => $this->queue->enqueue($company, 'invoice', 'Document', Uuid::v7()->toRfc4122(), $now));
        }
        $this->companyContext->clear();

        $due = $this->outbox->findDue($now->modify('+1 minute'), 100);

        $found = [];
        foreach ($due as $item) {
            $found[$item->companyId->toString()] = $item->communicationId;
        }
        self::assertSame($ids, $found, 'One narrow function lists due work across all companies, as (company, id) pairs.');

        $this->companyContext->set($companyA);
        self::assertSame(1, $this->countRows(), 'A normal runtime session in company A still sees only company A\'s rows.');
        $this->companyContext->set($companyB);
        self::assertSame(1, $this->countRows());
    }

    public function testTheSweeperFunctionReturnsNothingButIdentifiers(): void
    {
        $this->companyContext->set(CompanyId::generate());
        $this->transactions->transactional(fn (): string => $this->queue->enqueue($this->companyContext->companyId(), 'invoice', 'Document', Uuid::v7()->toRfc4122(), new \DateTimeImmutable()));
        $this->companyContext->clear();

        /** @var list<string> $columns */
        $columns = array_keys((array) $this->connection->fetchAssociative('SELECT * FROM at_communications_due(now() + interval \'1 minute\', 10, 10)'));

        self::assertSame(['company_id', 'id'], $columns);
    }

    public function testTheRuntimeRoleCannotReadTheTableWithoutACompanyContext(): void
    {
        $this->companyContext->clear();

        $this->expectException(\Doctrine\DBAL\Exception::class);

        $this->connection->fetchOne('SELECT count(*) FROM at_communications');
    }

    private function countRows(): int
    {
        $count = $this->transactions->transactional(fn (): mixed => $this->connection->fetchOne('SELECT count(*) FROM at_communications'));

        return is_numeric($count) ? (int) $count : 0;
    }

    private function truncate(): void
    {
        /** @var Connection $owner */
        $owner = self::getContainer()->get('doctrine.dbal.migrations_connection');
        $owner->executeStatement('TRUNCATE at_communications');
    }
}
