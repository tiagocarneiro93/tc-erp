<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Application\Query;

use App\Fiscal\Application\Query\ExportSaft;
use App\Fiscal\Application\Query\ExportSaftHandler;
use App\Fiscal\Domain\Exception\CompanyProfileMissing;
use App\Fiscal\Domain\Exception\InvalidSaftPeriod;
use App\Fiscal\Domain\Saft\SaftExportPeriod;
use App\Fiscal\Domain\Saft\SaftFileGenerator;
use App\Fiscal\Domain\Saft\SaftGenerationSummary;
use App\Fiscal\Domain\Saft\SaftSchemaValidator;
use App\Fiscal\Domain\Saft\SaftValidationFailed;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Company\CompanyFiscalIdentity;
use App\Shared\Domain\Company\CompanyFiscalIdentityProvider;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Output\ArchivedFile;
use App\Shared\Domain\Output\FileArchive;
use App\Shared\Domain\Security\PermissionChecker;
use PHPUnit\Framework\TestCase;

final class ExportSaftHandlerTest extends TestCase
{
    /** @var list<string> */
    private array $generatedPaths = [];

    public function recordGeneratedPath(string $path): void
    {
        $this->generatedPaths[] = $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->generatedPaths as $path) {
            @unlink($path);
        }
    }

    public function testAValidFileIsReturnedWithItsHashAndAReadableName(): void
    {
        $result = $this->handler(errors: [])(new ExportSaft('2026-01-01', '2026-03-31'));

        self::assertFileExists($result->path);
        self::assertSame('SAFT_508025090_20260101_20260331.xml', $result->filename);
        self::assertSame(hash_file('sha256', $result->path), $result->sha256);
        self::assertSame(\strlen('<AuditFile/>'), $result->sizeBytes);
        self::assertSame('0192e0f0-0000-7000-8000-0000000000aa', $result->storedFileId);
        self::assertSame(2, $result->summary->invoices);
        @unlink($result->path);
    }

    public function testAFileThatDoesNotValidateIsNeverOfferedArchivedOrKept(): void
    {
        $handler = $this->handler(errors: ['line 2: Element \'Header\': missing'], archiveMustNotBeUsed: true);

        try {
            $handler(new ExportSaft('2026-01-01', '2026-03-31'));
            self::fail('Expected the invalid file to be refused.');
        } catch (SaftValidationFailed $e) {
            self::assertSame(['line 2: Element \'Header\': missing'], $e->errors);
        }

        self::assertCount(1, $this->generatedPaths);
        self::assertFileDoesNotExist($this->generatedPaths[0], 'The invalid file must not be left lying around.');
    }

    public function testWithoutReportsAccessNothingIsGenerated(): void
    {
        $this->expectException(PermissionDenied::class);

        try {
            $this->handler(errors: [], granted: false)(new ExportSaft('2026-01-01', '2026-03-31'));
        } finally {
            self::assertSame([], $this->generatedPaths);
        }
    }

    public function testAMalformedDateIsRejectedBeforeAnythingIsGenerated(): void
    {
        $this->expectException(InvalidSaftPeriod::class);

        try {
            $this->handler(errors: [])(new ExportSaft('2026-02-30', '2026-03-31'));
        } finally {
            self::assertSame([], $this->generatedPaths);
        }
    }

    public function testACompanyWithoutAProfileCannotExport(): void
    {
        $this->expectException(CompanyProfileMissing::class);

        $this->handler(errors: [], identity: null)(new ExportSaft('2026-01-01', '2026-03-31'));
    }

    /**
     * @param list<string> $errors
     */
    private function handler(array $errors, bool $granted = true, ?CompanyFiscalIdentity $identity = new CompanyFiscalIdentity('508025090', 'Empresa Lda', null, null, null, null, 'PT', null, null, false), bool $archiveMustNotBeUsed = false): ExportSaftHandler
    {
        $generator = new class($this) implements SaftFileGenerator {
            public function __construct(private readonly ExportSaftHandlerTest $test)
            {
            }

            public function generate(CompanyId $companyId, CompanyFiscalIdentity $company, SaftExportPeriod $period, \DateTimeImmutable $createdAt, string $targetPath): SaftGenerationSummary
            {
                $this->test->recordGeneratedPath($targetPath);
                file_put_contents($targetPath, '<AuditFile/>');

                return new SaftGenerationSummary(2, 0, 0, 1, 1);
            }
        };

        $validator = new class($errors) implements SaftSchemaValidator {
            /** @param list<string> $errors */
            public function __construct(private readonly array $errors)
            {
            }

            public function validate(string $path): array
            {
                return $this->errors;
            }
        };

        $companies = $this->createStub(CompanyFiscalIdentityProvider::class);
        $companies->method('forCompany')->willReturn($identity);

        $permissions = $this->createStub(PermissionChecker::class);
        $permissions->method('isGranted')->willReturn($granted);

        $context = $this->createStub(CompanyContext::class);
        $context->method('companyId')->willReturn(CompanyId::generate());

        $clock = new class implements Clock {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-04-01T09:00:00+00:00');
            }
        };

        if ($archiveMustNotBeUsed) {
            $archive = $this->createMock(FileArchive::class);
            $archive->expects(self::never())->method('storeFile');
        } else {
            $archive = $this->createStub(FileArchive::class);
            $archive->method('storeFile')->willReturn(new ArchivedFile('0192e0f0-0000-7000-8000-0000000000aa', 'saft', str_repeat('a', 64), 12, new \DateTimeImmutable('2026-04-01T09:00:00+00:00')));
        }

        return new ExportSaftHandler($generator, $validator, $companies, $archive, $permissions, $context, $clock);
    }
}
