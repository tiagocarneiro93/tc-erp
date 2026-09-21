<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-2.md task 2.11: `GET /companies/{c}/documents` (list,
 * filterable) and `GET /companies/{c}/documents/{id}` (detail) — the read
 * side the web app's document list/detail screens need, not built by any
 * earlier task (2.6-2.10 only ever wrote to `documents`).
 */
final class DocumentsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testAnIssuedDocumentAppearsInTheListAndItsDetail(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $documentId = $this->issueInvoice($client, $companyId, '100.00');

        $client->request('GET', "/api/v1/companies/{$companyId}/documents", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{id: string, document_type: string, status: string, gross_total: string, open_amount: string}>} $list */
        $list = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $list['items']);
        self::assertSame($documentId, $list['items'][0]['id']);
        self::assertSame('FT', $list['items'][0]['document_type']);
        self::assertSame('N', $list['items'][0]['status']);
        self::assertSame('100.00', $list['items'][0]['gross_total']);
        self::assertSame('100.00', $list['items'][0]['open_amount']);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/{$documentId}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{id: string, document_type: string, lines: list<array{product_code: string}>} $detail */
        $detail = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($documentId, $detail['id']);
        self::assertCount(1, $detail['lines']);
        self::assertSame('SKU-1', $detail['lines'][0]['product_code']);
    }

    public function testTheListFiltersByTypeAndStatus(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $this->issueInvoice($client, $companyId, '10.00');

        $client->request('GET', "/api/v1/companies/{$companyId}/documents?type=NC", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<mixed>} $list */
        $list = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(0, $list['items']);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents?type=FT&status=N", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<mixed>} $list */
        $list = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $list['items']);
    }

    public function testAnUnknownDocumentIs404(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/documents/00000000-0000-7000-8000-000000000000", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function issueInvoice(KernelBrowser $client, string $companyId, string $unitPrice): string
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
                    ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => $unitPrice, 'tax_region' => 'PT', 'tax_code' => 'ISE', 'exemption_reason_code' => 'M99'],
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

        $client->request('POST', "/api/v1/companies/{$companyId}/series/{$created['id']}/activate", server: self::HEADERS, content: json_encode([
            'validation_code' => 'ABC123',
        ], \JSON_THROW_ON_ERROR));
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

        $user = User::register(UserId::generate(), $email, 'Documents List Flow User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('documents-list-%s@example.test', bin2hex(random_bytes(8)));
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
