<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Application;

use App\Output\Application\StoredFiles;
use App\Output\Domain\InvalidStoredFileKind;
use App\Output\Domain\ObjectNotFound;
use App\Output\Domain\ObjectStorage;
use App\Output\Domain\StoredFile;
use App\Output\Domain\StoredFileIntegrityViolation;
use App\Output\Domain\StoredFileKind;
use App\Output\Domain\StoredFileNotFound;
use App\Output\Domain\StoredFileRepository;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

/**
 * The archive's own rules against in-memory fakes of storage and register:
 * what is recorded, what is verified, in which order things are written.
 */
final class StoredFilesTest extends TestCase
{
    private InMemoryObjectStorage $storage;
    private InMemoryStoredFileRepository $repository;
    private StoredFiles $archive;
    private CompanyId $company;

    protected function setUp(): void
    {
        $this->storage = new InMemoryObjectStorage();
        $this->repository = new InMemoryStoredFileRepository();
        $clock = new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-03-05T10:00:00+00:00');
            }
        };
        $this->archive = new StoredFiles($this->repository, $this->storage, $clock);
        $this->company = CompanyId::generate();
    }

    public function testContentsRoundTripByteForByteAndTheHashAndSizeAreRecorded(): void
    {
        $bytes = implode('', array_map('chr', range(0, 255))).random_bytes(4096);

        $archived = $this->archive->storeContents($this->company, 'sealed_pdf', 'Document', 'doc-1', $bytes);

        self::assertSame(hash('sha256', $bytes), $archived->sha256);
        self::assertSame(\strlen($bytes), $archived->size);
        self::assertSame('sealed_pdf', $archived->kind);
        self::assertSame($bytes, $this->archive->contents($this->company, $archived->id));
    }

    public function testAFileOnDiskIsArchivedAndCopiedBackIdentically(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'archive-src-');
        $target = tempnam(sys_get_temp_dir(), 'archive-dst-');
        file_put_contents((string) $source, random_bytes(3 * (1 << 20) + 17));

        try {
            $archived = $this->archive->storeFile($this->company, 'saft', 'SaftExport', '2026', (string) $source);
            $this->archive->copyTo($this->company, $archived->id, (string) $target);

            self::assertFileEquals((string) $source, (string) $target);
            self::assertSame(hash_file('sha256', (string) $source), $archived->sha256);
        } finally {
            @unlink((string) $source);
            @unlink((string) $target);
        }
    }

    public function testTheObjectIsWrittenBeforeTheRowSoARowNeverPointsAtNothing(): void
    {
        $this->storage->failOnPut = true;

        try {
            $this->archive->storeContents($this->company, 'saft', 'SaftExport', '2026', 'x');
            self::fail('Expected the upload failure to propagate.');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertSame([], $this->repository->all(), 'No register row for an object that was never stored.');
    }

    public function testBytesThatNoLongerMatchTheirRecordedHashAreRefusedOnRead(): void
    {
        $archived = $this->archive->storeContents($this->company, 'sealed_pdf', 'Document', 'doc-1', 'the original bytes');
        $this->storage->tamper('the original bytez');

        $this->expectException(StoredFileIntegrityViolation::class);

        $this->archive->contents($this->company, $archived->id);
    }

    public function testATamperedFileIsNeverLeftBehindByACopy(): void
    {
        $archived = $this->archive->storeContents($this->company, 'sealed_pdf', 'Document', 'doc-1', 'the original bytes');
        $this->storage->tamper('something else entirely');
        $target = (string) tempnam(sys_get_temp_dir(), 'archive-bad-');

        try {
            $this->archive->copyTo($this->company, $archived->id, $target);
            self::fail('Expected the integrity violation.');
        } catch (StoredFileIntegrityViolation) {
            self::assertFileDoesNotExist($target);
        } finally {
            @unlink($target);
        }
    }

    public function testAnUnknownFileIdIsNotFound(): void
    {
        $this->expectException(StoredFileNotFound::class);

        $this->archive->contents($this->company, '0192e0f0-0000-7000-8000-000000000001');
    }

    public function testAnotherCompanysFileIsNotFoundEvenWithItsId(): void
    {
        $archived = $this->archive->storeContents($this->company, 'sealed_pdf', 'Document', 'doc-1', 'secret');

        $this->expectException(StoredFileNotFound::class);

        $this->archive->contents(CompanyId::generate(), $archived->id);
    }

    public function testAnUnknownKindIsRefusedBeforeAnythingIsStored(): void
    {
        try {
            $this->archive->storeContents($this->company, 'invoice_scan', 'Document', 'doc-1', 'x');
            self::fail('Expected the kind to be refused.');
        } catch (InvalidStoredFileKind) {
            self::assertSame([], $this->repository->all());
            self::assertSame(0, $this->storage->count());
        }
    }

    public function testKeysAreScopedByCompanyKindAndYear(): void
    {
        $archived = $this->archive->storeContents($this->company, 'saft', 'SaftExport', '2026', 'x');

        $file = $this->repository->find($this->company, $archived->id);
        self::assertNotNull($file);
        self::assertSame(\sprintf('%s/saft/2026/%s', $this->company->toString(), $archived->id), $file->storageKey);
    }

    public function testTheFirstFileOfASubjectIsTheOneFound(): void
    {
        $first = $this->archive->storeContents($this->company, 'sealed_pdf', 'Document', 'doc-1', 'one');
        $this->archive->storeContents($this->company, 'sealed_pdf', 'Document', 'doc-1', 'two');

        $found = $this->archive->findBySubject($this->company, 'sealed_pdf', 'Document', 'doc-1');

        self::assertNotNull($found);
        self::assertSame($first->id, $found->id);
        self::assertNull($this->archive->findBySubject($this->company, 'sealed_pdf', 'Document', 'other'));
        self::assertNull($this->archive->findBySubject($this->company, 'saft', 'Document', 'doc-1'));
    }
}

final class InMemoryObjectStorage implements ObjectStorage
{
    public bool $failOnPut = false;

    /** @var array<string, string> */
    private array $objects = [];

    public function put(string $key, $stream, string $contentType): void
    {
        if ($this->failOnPut) {
            throw new \RuntimeException('upload failed');
        }

        $this->objects[$key] = (string) stream_get_contents($stream);
    }

    public function get(string $key)
    {
        if (!isset($this->objects[$key])) {
            throw new ObjectNotFound($key);
        }

        $stream = fopen('php://memory', 'r+');
        \assert(false !== $stream);
        fwrite($stream, $this->objects[$key]);
        rewind($stream);

        return $stream;
    }

    public function exists(string $key): bool
    {
        return isset($this->objects[$key]);
    }

    public function tamper(string $newBytes): void
    {
        foreach (array_keys($this->objects) as $key) {
            $this->objects[$key] = $newBytes;
        }
    }

    public function count(): int
    {
        return \count($this->objects);
    }
}

final class InMemoryStoredFileRepository implements StoredFileRepository
{
    /** @var list<StoredFile> */
    private array $files = [];

    public function add(StoredFile $file): void
    {
        $this->files[] = $file;
    }

    public function find(CompanyId $companyId, string $id): ?StoredFile
    {
        foreach ($this->files as $file) {
            if ($file->id === $id && $file->companyId->equals($companyId)) {
                return $file;
            }
        }

        return null;
    }

    public function findFirstBySubject(CompanyId $companyId, StoredFileKind $kind, string $subjectType, string $subjectId): ?StoredFile
    {
        foreach ($this->files as $file) {
            if ($file->companyId->equals($companyId) && $file->kind === $kind && $file->subjectType === $subjectType && $file->subjectId === $subjectId) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return list<StoredFile>
     */
    public function all(): array
    {
        return $this->files;
    }
}
