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
 * docs/plans/phase-2.md task 2.11: `GET /companies/{c}/receipts` and
 * `GET /companies/{c}/receipts/{id}` — the read side task 2.9 didn't
 * build (only issuance).
 */
final class ReceiptsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testAnIssuedReceiptAppearsInTheListAndItsDetail(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);
        $ftSeriesId = $this->createActiveSeries($client, $companyId, 'FT', '2026A');
        $rgSeriesId = $this->createActiveSeries($client, $companyId, 'RG', '2026A');

        $client->request('POST', "/api/v1/companies/{$companyId}/drafts", server: self::HEADERS, content: json_encode([
            'document_type' => 'FT',
            'payload' => [
                'series_id' => $ftSeriesId,
                'pricing_mode' => 'net',
                'rounding_method' => 'per_line',
                'date' => '2026-01-01',
                'lines' => [
                    ['product_code' => 'SKU-1', 'description' => 'Widget', 'product_type' => 'P', 'unit_code' => 'UN', 'quantity' => '1', 'unit_price' => '80.00', 'tax_region' => 'PT', 'tax_code' => 'ISE', 'exemption_reason_code' => 'M99'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $draft */
        $draft = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/documents/drafts/{$draft['id']}/issue", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'issue-'.$draft['id']], content: '{}');
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $invoice */
        $invoice = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS + ['HTTP_Idempotency-Key' => 'rg-list-test'], content: json_encode([
            'series_id' => $rgSeriesId,
            'payment_method' => 'cash',
            'allocations' => [['document_id' => $invoice['id'], 'amount' => '80.00']],
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $receipt */
        $receipt = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('GET', "/api/v1/companies/{$companyId}/receipts", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{id: string, total: string, status: string}>} $list */
        $list = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $list['items']);
        self::assertSame($receipt['id'], $list['items'][0]['id']);
        self::assertSame('80.00', $list['items'][0]['total']);

        $client->request('GET', "/api/v1/companies/{$companyId}/receipts/{$receipt['id']}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{id: string, allocations: list<array{document_id: string, amount: string}>} $detail */
        $detail = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $detail['allocations']);
        self::assertSame($invoice['id'], $detail['allocations'][0]['document_id']);
        self::assertSame('80.00', $detail['allocations'][0]['amount']);
    }

    public function testAnUnknownReceiptIs404(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/receipts/00000000-0000-7000-8000-000000000000", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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

        $user = User::register(UserId::generate(), $email, 'Receipts List Flow User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('receipts-list-%s@example.test', bin2hex(random_bytes(8)));
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
