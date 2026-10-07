<?php

declare(strict_types=1);

namespace App\Tests\Functional\Output;

use App\Output\Application\SealedDocumentPdfs;
use App\Output\Domain\PrintableDocumentNotFound;
use App\Output\Infrastructure\Sealing\FakeElectronicSealer;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\TransactionManager;
use App\Shared\Infrastructure\Company\RequestCompanyContext;
use App\Tests\Support\FiscalFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * docs/plans/phase-3.md task 3.6 on a document issued through the real use
 * case, real PostgreSQL and real object storage: sealed once, byte-identical
 * ever after.
 */
final class SealedDocumentPdfsTest extends WebTestCase
{
    use FiscalFlowHelpers;

    public function testTheSealedPdfIsMadeOnceAndEveryLaterCallReturnsTheSameBytes(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');
        $company = CompanyId::fromString($companyId);

        $first = $this->obtain($company, $documentId);
        $second = $this->obtain($company, $documentId);

        self::assertTrue($first->sealedNow);
        self::assertFalse($second->sealedNow);
        self::assertSame($first->bytes, $second->bytes);
        self::assertStringStartsWith('%PDF-', $first->bytes);
        self::assertStringContainsString(FakeElectronicSealer::MARKER, $first->bytes);
        self::assertSame($first->file->id, $second->file->id);
        self::assertSame(hash('sha256', $first->bytes), $first->file->sha256);

        $rows = $this->connection($companyId)->fetchAllAssociative("SELECT kind, subject_type, subject_id FROM stored_files WHERE company_id = ? AND kind = 'sealed_pdf'", [$companyId]);
        self::assertSame([['kind' => 'sealed_pdf', 'subject_type' => 'Document', 'subject_id' => $documentId]], $rows, 'Exactly one sealed file per document.');
        self::assertSame([], $this->connection($companyId)->fetchAllAssociative('SELECT 1 FROM document_prints WHERE company_id = ?', [$companyId]), 'Sealing is not a hand-out: the print log is untouched.');
    }

    public function testADraftCannotBeSealed(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $this->expectException(PrintableDocumentNotFound::class);

        $this->obtain(CompanyId::fromString($companyId), '0192e0f0-0000-7000-8000-00000000dead');
    }

    private function obtain(CompanyId $company, string $documentId): \App\Output\Application\SealedPdf
    {
        $container = static::getContainer();
        /** @var RequestCompanyContext $context */
        $context = $container->get(RequestCompanyContext::class);
        /** @var TransactionManager $transactions */
        $transactions = $container->get(TransactionManager::class);
        /** @var SealedDocumentPdfs $sealed */
        $sealed = $container->get(SealedDocumentPdfs::class);

        $context->set($company);

        try {
            return $transactions->transactional(static fn () => $sealed->obtain($company, $documentId));
        } finally {
            $context->clear();
        }
    }
}
