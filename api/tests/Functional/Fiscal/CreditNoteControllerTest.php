<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-2.md task 2.7: `POST /documents/{id}/credit-note`
 * prefills a new NC draft from an existing document's lines, and
 * Despacho 8632/2014 §3.3.7's two rejections (cancelled, already fully
 * rectified). Ordinary issuance of FT/FS/FR/NC/ND itself needs no
 * type-specific code — {@see IssueDraftControllerTest} already covers the
 * generic pipeline every document type goes through.
 */
final class CreditNoteControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testCreditNoteIsPrefilledFromTheOriginalDocumentsLines(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');
        $documentId = $this->issueDocument($client, $companyId, $ftSeriesId, 'FT', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '2', 'unit_price' => '50.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/credit-note", server: self::HEADERS, content: json_encode(['reason' => 'Devolução'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$body['id']}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{document_type: string, payload: array{references: list<array{referenced_document_no: string, reason: ?string}>, lines: list<array{quantity: string, unit_price: string}>}} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('NC', $draft['document_type']);
        self::assertCount(1, $draft['payload']['lines']);
        self::assertSame('2.000000', $draft['payload']['lines'][0]['quantity']);
        self::assertSame('50.000000', $draft['payload']['lines'][0]['unit_price']);
        self::assertCount(1, $draft['payload']['references']);
        self::assertSame('Devolução', $draft['payload']['references'][0]['reason']);
    }

    public function testCreditNoteAgainstAnAlreadyFullyRectifiedDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');
        $ncSeriesId = $this->createActiveSeries($client, $companyId, 'NC', '2026A');

        $documentId = $this->issueDocument($client, $companyId, $ftSeriesId, 'FT', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        // First credit note covers the full amount.
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/credit-note", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $creditNoteDraft */
        $creditNoteDraft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$creditNoteDraft['id']}", server: self::HEADERS, content: json_encode([
            'payload' => [
                'series_id' => $ncSeriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [
                    ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
                ],
                'references' => [['referenced_document_no' => $this->documentNo($companyId, $documentId), 'reason' => 'Devolução total']],
            ],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$creditNoteDraft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'nc-full'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // A second credit note against the same, now-fully-rectified document must be rejected.
        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/credit-note", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreditNoteAgainstACancelledDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');
        $documentId = $this->issueDocument($client, $companyId, $ftSeriesId, 'FT', [
            ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
        ]);

        // Cancellation itself is task 2.10 — not built yet, so this test
        // sets the status directly to prove §3.3.7's other rejection reason.
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        $connection->executeStatement('INSERT INTO document_status_events (id, company_id, document_id, status, occurred_at) VALUES (gen_random_uuid(), ?, ?, ?, now())', [$companyId, $documentId, 'A']);
        $connection->executeStatement('UPDATE documents SET status = ? WHERE company_id = ? AND id = ?', ['A', $companyId, $documentId]);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/credit-note", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreditNoteAgainstAnUnknownDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/00000000-0000-7000-8000-000000000000/credit-note", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function issueDocument(KernelBrowser $client, string $companyId, string $seriesId, string $documentType, array $lines): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'payload' => [
                'series_id' => $seriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => $lines,
            ],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'issue-'.$draft['id']], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $document */
        $document = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $document['id'];
    }

    private function documentNo(string $companyId, string $documentId): string
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        /** @var array{document_no: string} $row */
        $row = $connection->fetchAssociative('SELECT document_no FROM documents WHERE company_id = ? AND id = ?', [$companyId, $documentId]);

        return $row['document_no'];
    }

    private function createActiveSeries(KernelBrowser $client, string $companyId, string $documentType, string $code): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/series", server: self::HEADERS, content: json_encode([
            'document_type' => $documentType,
            'code' => $code,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$created['id']}/activate", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        return $created['id'];
    }

    private function createCompany(KernelBrowser $client, string $legalName = 'A Company Lda'): string
    {
        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => $this->uniqueNif(),
            'legal_name' => $legalName,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    private function registerAndLogIn(KernelBrowser $client): UserId
    {
        $email = $this->uniqueEmail();
        $password = 'owner-password';

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Credit Note Flow User', $hasher->hash($password), new \DateTimeImmutable());
        $user->changePassword($hasher->hash($password), new \DateTimeImmutable());
        $users->save($user);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return $user->id();
    }

    private function uniqueEmail(): string
    {
        return \sprintf('credit-note-%s@example.test', bin2hex(random_bytes(8)));
    }

    private function uniqueNif(): string
    {
        do {
            $prefix = (string) random_int(10_000_000, 99_999_999);
            $sum = 0;
            for ($position = 0; $position < 8; ++$position) {
                $sum += (int) $prefix[$position] * (9 - $position);
            }
            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? 0 : 11 - $remainder;
            $nif = $prefix.$checkDigit;
        } while (!Nif::isValid($nif));

        return $nif;
    }
}
