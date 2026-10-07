<?php

declare(strict_types=1);

namespace App\Tests\Integration\Output;

use App\Output\Domain\ObjectNotFound;
use App\Output\Domain\ObjectStorage;
use App\Output\Domain\StoredFileIntegrityViolation;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Output\FileArchive;
use App\Shared\Domain\TransactionManager;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * docs/plans/phase-3.md task 3.4 against the real thing: PostgreSQL with its
 * RLS policies and constraints, and the MinIO of `docker-compose.yml`
 * (`S3_ENDPOINT` — these tests need it up, like they need PostgreSQL).
 */
final class StoredFilesIntegrationTest extends KernelTestCase
{
    private FileArchive $archive;
    private ObjectStorage $storage;
    private TransactionManager $transactions;
    private RequestCompanyContext $companyContext;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var FileArchive $archive */
        $archive = $container->get(FileArchive::class);
        $this->archive = $archive;
        /** @var ObjectStorage $storage */
        $storage = $container->get(ObjectStorage::class);
        $this->storage = $storage;
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

    public function testBinaryContentRoundTripsByteForByteThroughRealStorage(): void
    {
        $company = $this->asCompany();
        $bytes = implode('', array_map('chr', range(0, 255))).random_bytes(200_000);

        $archived = $this->inTransaction(fn () => $this->archive->storeContents($company, 'sealed_pdf', 'Document', Uuid::v7()->toRfc4122(), $bytes));
        $read = $this->inTransaction(fn () => $this->archive->contents($company, $archived->id));

        self::assertSame($bytes, $read);
        self::assertSame(hash('sha256', $bytes), $archived->sha256);
    }

    public function testALargeFileStreamsThroughAndBack(): void
    {
        $company = $this->asCompany();
        $source = (string) tempnam(sys_get_temp_dir(), 'big-src-');
        $target = (string) tempnam(sys_get_temp_dir(), 'big-dst-');
        file_put_contents($source, random_bytes(6 * (1 << 20) + 3));

        try {
            $archived = $this->inTransaction(fn () => $this->archive->storeFile($company, 'saft', 'SaftExport', '2026', $source));
            $this->inTransaction(function () use ($company, $archived, $target): void {
                $this->archive->copyTo($company, $archived->id, $target);
            });

            self::assertFileEquals($source, $target);
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    public function testBytesChangedBehindTheArchivesBackAreRefused(): void
    {
        $company = $this->asCompany();
        $archived = $this->inTransaction(fn () => $this->archive->storeContents($company, 'sealed_pdf', 'Document', 'doc-tamper', 'the original'));
        $key = $this->inTransaction(fn (): mixed => $this->connection->fetchOne('SELECT storage_key FROM stored_files WHERE id = ?', [$archived->id]));
        \assert(\is_string($key));

        $replacement = fopen('php://memory', 'r+');
        self::assertNotFalse($replacement);
        fwrite($replacement, 'a forged replacement');
        rewind($replacement);
        $this->storage->put($key, $replacement, 'application/pdf');

        $this->expectException(StoredFileIntegrityViolation::class);

        $this->inTransaction(fn () => $this->archive->contents($company, $archived->id));
    }

    public function testExistsAndMissingObjectsBehaveLikeTheContract(): void
    {
        self::assertFalse($this->storage->exists('nobody/here/'.Uuid::v7()->toRfc4122()));

        $this->expectException(ObjectNotFound::class);

        $this->storage->get('nobody/here/'.Uuid::v7()->toRfc4122());
    }

    public function testACompanyCannotReadAnotherCompanysFile(): void
    {
        $owner = $this->asCompany();
        $archived = $this->inTransaction(fn () => $this->archive->storeContents($owner, 'sealed_pdf', 'Document', 'doc-private', 'private bytes'));

        $other = $this->asCompany();

        try {
            $this->inTransaction(fn () => $this->archive->contents($other, $archived->id));
            self::fail('Another company must not reach the file.');
        } catch (\App\Output\Domain\StoredFileNotFound) {
            // RLS hides the row, so the object is unreachable
        }

        $this->companyContext->set($owner);
        $visible = $this->inTransaction(fn (): mixed => $this->connection->fetchOne('SELECT count(*) FROM stored_files'));
        \assert(\is_int($visible) || \is_string($visible));
        self::assertGreaterThanOrEqual(1, (int) $visible);

        $this->companyContext->set($other);
        $visibleToOther = $this->inTransaction(fn (): mixed => $this->connection->fetchOne('SELECT count(*) FROM stored_files'));
        \assert(\is_int($visibleToOther) || \is_string($visibleToOther));
        self::assertSame(0, (int) $visibleToOther);
    }

    public function testRegisterRowsCannotBeUpdatedOrDeleted(): void
    {
        $company = $this->asCompany();
        $archived = $this->inTransaction(fn () => $this->archive->storeContents($company, 'saft', 'SaftExport', '2026', '<x/>'));

        $refused = 0;

        foreach (['UPDATE stored_files SET size = 0 WHERE id = ?', 'DELETE FROM stored_files WHERE id = ?'] as $statement) {
            try {
                $this->inTransaction(fn () => $this->connection->executeStatement($statement, [$archived->id]));
            } catch (DbalException) {
                ++$refused; // app_runtime has neither UPDATE nor DELETE on stored_files
            }
        }

        self::assertSame(2, $refused, 'Both the UPDATE and the DELETE must be refused by the database.');
    }

    public function testTheDatabaseRefusesAKindOutsideTheEnum(): void
    {
        $company = $this->asCompany();

        $this->expectException(DbalException::class);

        $this->inTransaction(fn () => $this->connection->insert('stored_files', [
            'id' => Uuid::v7()->toRfc4122(),
            'company_id' => $company->toString(),
            'kind' => 'invoice_scan',
            'subject_type' => 'Document',
            'subject_id' => 'x',
            'storage_key' => 'k/'.Uuid::v7()->toRfc4122(),
            'sha256' => str_repeat('a', 64),
            'size' => 1,
            'created_at' => '2026-03-05 10:00:00+00',
        ]));
    }

    private function asCompany(): CompanyId
    {
        $company = CompanyId::generate();
        $this->companyContext->set($company);

        return $company;
    }

    /**
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    private function inTransaction(\Closure $operation): mixed
    {
        return $this->transactions->transactional($operation);
    }
}
