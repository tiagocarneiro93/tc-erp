<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Application;

use App\Output\Application\SealedDocumentPdfs;
use App\Output\Domain\DocumentPdfRenderer;
use App\Output\Domain\DocumentPrint;
use App\Output\Domain\DocumentPrintRepository;
use App\Output\Domain\ElectronicSealer;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Output\Domain\SealingFailed;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Shared\Domain\Output\ArchivedFile;
use App\Shared\Domain\Output\FileArchive;
use App\Tests\Support\PrintableDocuments;
use PHPUnit\Framework\TestCase;

/**
 * "Sealed once": the seal is paid for and the bytes fixed on the first call;
 * every later call hands back the stored file untouched.
 */
final class SealedDocumentPdfsTest extends TestCase
{
    private CompanyId $company;
    private PrintableDocument $document;
    private CountingRenderer $renderer;
    private CountingSealer $sealer;
    private MemoryArchive $archive;
    private MemoryPrints $prints;
    private SealedDocumentPdfs $sealed;

    protected function setUp(): void
    {
        $this->company = CompanyId::generate();
        $this->document = PrintableDocuments::invoice();
        $this->renderer = new CountingRenderer();
        $this->sealer = new CountingSealer();
        $this->archive = new MemoryArchive();
        $this->prints = new MemoryPrints();
        $document = $this->document;
        $reader = new class($document) implements PrintableDocumentReader {
            public function __construct(private readonly PrintableDocument $document)
            {
            }

            public function find(CompanyId $companyId, string $documentId): ?PrintableDocument
            {
                return $documentId === $this->document->id ? $this->document : null;
            }
        };
        $this->sealed = new SealedDocumentPdfs($reader, $this->renderer, $this->sealer, $this->archive, $this->prints);
    }

    public function testTheFirstCallRendersSealsAndArchivesTheSealedBytes(): void
    {
        $result = $this->sealed->obtain($this->company, $this->document->id);

        self::assertTrue($result->sealedNow);
        self::assertSame('Original', $result->copyLabel);
        self::assertSame('sealed(pdf:Original)', $result->bytes);
        self::assertSame('sealed_pdf', $result->file->kind);
        self::assertSame(hash('sha256', $result->bytes), $result->file->sha256);
        self::assertSame($result->bytes, $this->archive->contents($this->company, $result->file->id));
        self::assertSame(1, $this->sealer->calls);
    }

    public function testLaterCallsReturnTheStoredBytesWithoutRenderingOrSealingAgain(): void
    {
        $first = $this->sealed->obtain($this->company, $this->document->id);
        $second = $this->sealed->obtain($this->company, $this->document->id);
        $third = $this->sealed->obtain($this->company, $this->document->id);

        self::assertSame($first->bytes, $second->bytes);
        self::assertSame($first->bytes, $third->bytes);
        self::assertFalse($second->sealedNow);
        self::assertNull($second->copyLabel, 'The label is part of the stored bytes, not recorded separately.');
        self::assertSame($first->file->id, $second->file->id);
        self::assertSame(1, $this->sealer->calls);
        self::assertSame(1, $this->renderer->calls);
        self::assertCount(1, $this->archive->files);
    }

    public function testTheSealedFileCarriesTheLabelTheDocumentsPrintLogYieldsAtThatMoment(): void
    {
        $this->prints->count = 1;

        self::assertSame('Duplicado', $this->sealed->obtain($this->company, $this->document->id)->copyLabel);
    }

    public function testAFailedSealStoresNothingAndReturnsNothingUnsealed(): void
    {
        $this->sealer->failing = true;

        try {
            $this->sealed->obtain($this->company, $this->document->id);
            self::fail('Sealing was expected to fail.');
        } catch (SealingFailed) {
            self::assertCount(0, $this->archive->files);
        }

        $this->sealer->failing = false;

        self::assertTrue($this->sealed->obtain($this->company, $this->document->id)->sealedNow, 'A later attempt starts clean.');
    }

    public function testADocumentThatIsNotIssuedCannotBeSealed(): void
    {
        $this->expectException(PrintableDocumentNotFound::class);

        try {
            $this->sealed->obtain($this->company, '0192e0f0-0000-7000-8000-00000000dead');
        } finally {
            self::assertSame(0, $this->sealer->calls);
            self::assertCount(0, $this->archive->files);
        }
    }

    public function testTheDocumentIsLockedBeforeLookingForAnExistingFile(): void
    {
        $this->sealed->obtain($this->company, $this->document->id);

        self::assertSame([$this->document->id], $this->prints->locked);
    }
}

final class CountingRenderer implements DocumentPdfRenderer
{
    public int $calls = 0;

    public function render(PrintableDocument $document, string $copyLabel): string
    {
        ++$this->calls;

        return 'pdf:'.$copyLabel;
    }
}

final class CountingSealer implements ElectronicSealer
{
    public int $calls = 0;
    public bool $failing = false;

    public function seal(CompanyId $companyId, string $pdf): string
    {
        ++$this->calls;

        if ($this->failing) {
            throw new SealingFailed('provider down');
        }

        return 'sealed('.$pdf.')';
    }
}

final class MemoryPrints implements DocumentPrintRepository
{
    public int $count = 0;
    /** @var list<string> */
    public array $locked = [];

    public function add(DocumentPrint $print): void
    {
        ++$this->count;
    }

    public function lockDocument(CompanyId $companyId, string $documentId): void
    {
        $this->locked[] = $documentId;
    }

    public function countFor(CompanyId $companyId, string $documentId): int
    {
        return $this->count;
    }
}

final class MemoryArchive implements FileArchive
{
    /** @var array<string, array{file: ArchivedFile, subject: string, contents: string}> */
    public array $files = [];

    public function storeFile(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $sourcePath): ArchivedFile
    {
        return $this->storeContents($companyId, $kind, $subjectType, $subjectId, (string) file_get_contents($sourcePath));
    }

    public function storeContents(CompanyId $companyId, string $kind, string $subjectType, string $subjectId, string $contents): ArchivedFile
    {
        $id = 'file-'.(\count($this->files) + 1);
        $file = new ArchivedFile($id, $kind, hash('sha256', $contents), \strlen($contents), new \DateTimeImmutable('2026-10-07T10:00:00+00:00'));
        $this->files[$id] = ['file' => $file, 'subject' => $kind.'|'.$subjectType.'|'.$subjectId, 'contents' => $contents];

        return $file;
    }

    public function findBySubject(CompanyId $companyId, string $kind, string $subjectType, string $subjectId): ?ArchivedFile
    {
        foreach ($this->files as $entry) {
            if ($entry['subject'] === $kind.'|'.$subjectType.'|'.$subjectId) {
                return $entry['file'];
            }
        }

        return null;
    }

    public function contents(CompanyId $companyId, string $fileId): string
    {
        return $this->files[$fileId]['contents'];
    }

    public function copyTo(CompanyId $companyId, string $fileId, string $targetPath): void
    {
        file_put_contents($targetPath, $this->contents($companyId, $fileId));
    }
}
