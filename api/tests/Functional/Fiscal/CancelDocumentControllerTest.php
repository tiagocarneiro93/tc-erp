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
 * docs/plans/phase-2.md task 2.10: `POST /documents/{id}/cancel` — status
 * `A`, only for a document that never had external effect: no active NC
 * already rectifying it (Despacho 8632/2014 §3.3.8) and not yet
 * communicated to the AT (CIVA Art. 29.º §7, `docs/legal/civa-extracts.md`).
 */
final class CancelDocumentControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testAnUncommunicatedDocumentWithNoRectifyingNoteCancels(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueInvoice($client, $companyId);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: json_encode(['reason' => 'Duplicate, created in error'], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var array{status: string, status_reason: ?string} $row */
        $row = $this->fetchDocument($companyId, $documentId);
        self::assertSame('A', $row['status']);
        self::assertSame('Duplicate, created in error', $row['status_reason']);
    }

    public function testCancellingAnAlreadyCancelledDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueInvoice($client, $companyId);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCancellingADocumentWithAnActiveCreditNoteIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueInvoice($client, $companyId);
        $ncSeriesId = $this->createActiveSeries($client, $companyId, 'NC', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/credit-note", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $creditNoteDraft */
        $creditNoteDraft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/drafts/{$creditNoteDraft['id']}", server: self::HEADERS);
        /** @var array{payload: array<string, mixed>} $draftView */
        $draftView = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $payload = $draftView['payload'];
        $payload['series_id'] = $ncSeriesId;

        $client->request('PUT', "/api/v1/companies/{$companyId}/drafts/{$creditNoteDraft['id']}", server: self::HEADERS, content: json_encode(['payload' => $payload], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$creditNoteDraft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'nc-blocks-cancel'], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCancellingADocumentAlreadySettledByAReceiptIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueInvoice($client, $companyId);
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');

        // A partial payment is enough — paying an invoice is at least as
        // strong a signal it reached the customer as AT communication is.
        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'settles-ft'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $documentId, 'amount' => '1.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCancellingADocumentCommunicatedToTheAtIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueInvoice($client, $companyId);

        // The at_communications row is written at issuance (task 2.6/2.10's
        // own fix); simulate it having actually reached the AT, which
        // nothing in this phase does for real yet (Phase 3).
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        $connection->executeStatement(
            "UPDATE at_communications SET status = 'accepted' WHERE company_id = ? AND subject_type = 'Document' AND subject_id = ?",
            [$companyId, $documentId],
        );

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAnAtCommunicationThatNeverSucceededDoesNotBlockCancellation(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueInvoice($client, $companyId);

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        $connection->executeStatement(
            "UPDATE at_communications SET status = 'failed' WHERE company_id = ? AND subject_type = 'Document' AND subject_id = ?",
            [$companyId, $documentId],
        );

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/{$documentId}/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testCancellingAnUnknownDocumentIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/00000000-0000-7000-8000-000000000000/cancel", server: self::HEADERS, content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array{status: string, status_reason: ?string}
     */
    private function fetchDocument(string $companyId, string $documentId): array
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(\sprintf("SELECT set_config('app.company_id', %s, false)", $connection->quote($companyId)));
        /** @var array{status: string, status_reason: ?string} $row */
        $row = $connection->fetchAssociative('SELECT status, status_reason FROM documents WHERE company_id = ? AND id = ?', [$companyId, $documentId]);

        return $row;
    }

    private function issueInvoice(KernelBrowser $client, string $companyId): string
    {
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'payload' => [
                'series_id' => $ftSeriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [
                    ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
                ],
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

        $user = User::register(UserId::generate(), $email, 'Cancel Document Flow User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('cancel-document-%s@example.test', bin2hex(random_bytes(8)));
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
