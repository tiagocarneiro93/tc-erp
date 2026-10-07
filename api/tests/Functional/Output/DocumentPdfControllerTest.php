<?php

declare(strict_types=1);

namespace App\Tests\Functional\Output;

use App\Output\Domain\DocumentPdfRenderer;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Fiscal\PrintableDocumentReader;
use App\Tests\Support\FiscalFlowHelpers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-3.md task 3.5 end to end on documents issued through the
 * real use case: the endpoint, the print log and the copy labels, and the
 * guarantee that a PDF is a function of stored data alone.
 */
final class DocumentPdfControllerTest extends WebTestCase
{
    use FiscalFlowHelpers;

    public function testTheFirstDownloadIsTheOriginalAndEveryLaterOneACopy(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueOfType($client, $companyId, 'FT');

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$documentId}/pdf", server: self::HEADERS);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('X-Copy-Label', 'Original');
        self::assertStringContainsString('attachment; filename=FT_2026A_1.pdf', (string) $client->getResponse()->headers->get('Content-Disposition'));
        $first = (string) $client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF-', $first);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$documentId}/pdf?kind=print", server: self::HEADERS);
        self::assertResponseHeaderSame('X-Copy-Label', 'Duplicado');
        self::assertStringContainsString('inline', (string) $client->getResponse()->headers->get('Content-Disposition'));

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$documentId}/pdf", server: self::HEADERS);
        self::assertResponseHeaderSame('X-Copy-Label', 'Triplicado');

        self::assertNotSame($first, (string) $client->getResponse()->getContent(), 'A copy is a different file: it says so.');

        $rows = $this->connection($companyId)->fetchAllAssociative('SELECT kind, copy_label FROM document_prints WHERE company_id = ? ORDER BY occurred_at, id', [$companyId]);
        self::assertSame(
            [['kind' => 'download', 'copy_label' => 'Original'], ['kind' => 'print', 'copy_label' => 'Duplicado'], ['kind' => 'download', 'copy_label' => 'Triplicado']],
            $rows,
            'Every render is logged with its kind and label.',
        );
    }

    public function testADraftIsNotADocumentAndCannotBePrinted(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $seriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'payload' => ['series_id' => $seriesId, 'pricing_mode' => 'net', 'rounding_method' => 'per_line', 'date' => '2026-01-01', 'lines' => [$this->widgetLine()]],
        ], \JSON_THROW_ON_ERROR));
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$draft['id']}/pdf", server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame([], $this->connection($companyId)->fetchAllAssociative('SELECT 1 FROM document_prints WHERE company_id = ?', [$companyId]), 'A refused request is not a hand-out.');
    }

    public function testAnUnknownOrMalformedIdIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/not-a-uuid/pdf", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/0192e0f0-0000-7000-8000-000000000099/pdf", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnotherCompanysDocumentCannotBeReached(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $ownCompany = $this->createCompany($client, 'Own Lda');
        $foreignCompany = $this->createCompany($client, 'Foreign Lda');
        $foreignDocument = $this->issueOfType($client, $foreignCompany, 'FT');

        $client->request('GET', "/api/v1/companies/{$ownCompany}/documents/{$foreignDocument}/pdf", server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnUnauthenticatedCallerIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/companies/0192e0f0-0000-7000-8000-000000000001/documents/0192e0f0-0000-7000-8000-000000000002/pdf', server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTheSameDocumentRendersByteIdenticallyAndIgnoresLaterChangesToTheCustomerAndTheCompany(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client, 'Nome Original Lda');
        $customerId = $this->createCustomer($client, $companyId, 'Cliente Original', $this->uniqueNif());
        $documentId = $this->issueDocument($client, $companyId, $this->createActiveSeries($client, $companyId, 'FT', '2026A'), 'FT', extraPayload: ['customer_id' => $customerId]);

        $before = $this->renderDirectly($companyId, $documentId);
        sleep(1);
        self::assertSame($before, $this->renderDirectly($companyId, $documentId), 'Repeat renders are byte-identical.');

        // The customer changes address and the company changes name, address and VAT regime after issuance …
        $client->request('GET', "/api/v1/companies/{$companyId}/customers/{$customerId}", server: self::HEADERS);
        /** @var array<string, mixed> $customer */
        $customer = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        // (The customer's name and NIF are locked once a document exists — Despacho 8632/2014; the address is not.)
        $client->request('PUT', "/api/v1/companies/{$companyId}/customers/{$customerId}", server: self::HEADERS, content: json_encode(['address' => 'Outra Rua 99', 'postal_code' => '8000-001', 'city' => 'Faro'] + $customer, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'The test only means something if the address change was accepted.');

        $client->request('GET', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS);
        /** @var array<string, mixed> $profile */
        $profile = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $client->request('PUT', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS, content: json_encode(['legal_name' => 'Nome Novo da Empresa SA', 'address' => 'Avenida Nova 1', 'cash_vat' => true] + $profile, \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // … and the issued document still prints exactly as it was issued.
        self::assertSame($before, $this->renderDirectly($companyId, $documentId), 'A later change of name/address/regime never alters an old document (Despacho 8632/2014 §2.2.15).');
    }

    public function testTheIssuerIdentityIsFrozenIntoTheDocumentAtIssuance(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client, 'Empresa Congelada Lda');
        $documentId = $this->issueOfType($client, $companyId, 'FT');

        $snapshot = $this->connection($companyId)->fetchOne('SELECT issuer_snapshot FROM documents WHERE company_id = ? AND id = ?', [$companyId, $documentId]);
        self::assertIsString($snapshot);
        /** @var array{identity: array<string, mixed>} $decoded */
        $decoded = json_decode($snapshot, true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('Empresa Congelada Lda', $decoded['identity']['legal_name']);
        self::assertFalse($decoded['identity']['cash_vat']);
        self::assertArrayHasKey('nif', $decoded['identity']);
    }

    private function renderDirectly(string $companyId, string $documentId): string
    {
        $company = CompanyId::fromString($companyId);
        $connection = $this->connection($companyId);
        $connection->beginTransaction();

        try {
            /** @var PrintableDocumentReader $reader */
            $reader = static::getContainer()->get(PrintableDocumentReader::class);
            /** @var DocumentPdfRenderer $renderer */
            $renderer = static::getContainer()->get(DocumentPdfRenderer::class);
            $document = $reader->find($company, $documentId);
            self::assertNotNull($document);

            return $renderer->render($document, 'Original');
        } finally {
            $connection->rollBack();
        }
    }
}
