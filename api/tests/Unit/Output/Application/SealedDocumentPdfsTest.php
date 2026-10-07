<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Application;

use App\Output\Application\SealedDocumentPdfs;
use App\Output\Domain\DocumentPrint;
use App\Output\Domain\DocumentPrintKind;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Output\Domain\SealingFailed;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Tests\Support\Output\CountingRenderer;
use App\Tests\Support\Output\CountingSealer;
use App\Tests\Support\Output\MemoryArchive;
use App\Tests\Support\Output\MemoryPrints;
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
        $clock = new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-07T10:00:00+00:00');
            }
        };
        $this->sealed = new SealedDocumentPdfs($reader, $this->renderer, $this->sealer, $this->archive, $this->prints, $clock);
    }

    public function testTheFirstCallRendersSealsAndArchivesTheSealedBytes(): void
    {
        $result = $this->sealed->obtain($this->company, $this->document->id, 'user-1');

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
        $first = $this->sealed->obtain($this->company, $this->document->id, 'user-1');
        $second = $this->sealed->obtain($this->company, $this->document->id, 'user-1');
        $third = $this->sealed->obtain($this->company, $this->document->id, 'user-1');

        self::assertSame($first->bytes, $second->bytes);
        self::assertSame($first->bytes, $third->bytes);
        self::assertFalse($second->sealedNow);
        self::assertSame('Original', $second->copyLabel, 'The label of the stored file is recovered from its print record.');
        self::assertSame($first->file->id, $second->file->id);
        self::assertSame(1, $this->sealer->calls);
        self::assertSame(1, $this->renderer->calls);
        self::assertCount(1, $this->archive->files);
    }

    public function testTheSealedFileCarriesTheLabelTheDocumentsPrintLogYieldsAtThatMoment(): void
    {
        $this->prints->add(new DocumentPrint('p-1', $this->company, $this->document->id, DocumentPrintKind::Download, 'Original', 'user-1', new \DateTimeImmutable('2026-10-06T10:00:00+00:00')));

        $first = $this->sealed->obtain($this->company, $this->document->id, 'user-1');

        self::assertSame('Duplicado', $first->copyLabel);
        self::assertSame('sealed(pdf:Duplicado)', $first->bytes);
        self::assertSame('Duplicado', $this->sealed->obtain($this->company, $this->document->id, 'user-2')->copyLabel);
    }

    public function testSealingLogsOneElectronicHandOutAndLaterObtainsLogNothingMore(): void
    {
        $this->sealed->obtain($this->company, $this->document->id, 'user-1');
        $this->sealed->obtain($this->company, $this->document->id, 'user-2');

        self::assertCount(1, $this->prints->rows);
        self::assertSame(DocumentPrintKind::Email, $this->prints->rows[0]->kind);
        self::assertSame('Original', $this->prints->rows[0]->copyLabel);
        self::assertSame('user-1', $this->prints->rows[0]->userId);
    }

    public function testAFailedSealStoresNothingAndReturnsNothingUnsealed(): void
    {
        $this->sealer->failing = true;

        try {
            $this->sealed->obtain($this->company, $this->document->id, 'user-1');
            self::fail('Sealing was expected to fail.');
        } catch (SealingFailed) {
            self::assertCount(0, $this->archive->files);
        }

        $this->sealer->failing = false;

        self::assertTrue($this->sealed->obtain($this->company, $this->document->id, 'user-1')->sealedNow, 'A later attempt starts clean.');
    }

    public function testADocumentThatIsNotIssuedCannotBeSealed(): void
    {
        $this->expectException(PrintableDocumentNotFound::class);

        try {
            $this->sealed->obtain($this->company, '0192e0f0-0000-7000-8000-00000000dead', 'user-1');
        } finally {
            self::assertSame(0, $this->sealer->calls);
            self::assertCount(0, $this->archive->files);
        }
    }

    public function testTheDocumentIsLockedBeforeLookingForAnExistingFile(): void
    {
        $this->sealed->obtain($this->company, $this->document->id, 'user-1');

        self::assertSame([$this->document->id], $this->prints->locked);
    }
}
