<?php

declare(strict_types=1);

namespace App\Tests\Functional\Tax;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-2.md task 2.1: `/calculate` is the one place that turns
 * raw lines into a canonical calculation. No permission check beyond
 * company membership — any authenticated member may preview a
 * calculation, same as the global reference-data endpoints.
 */
final class CalculateControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testCalculatingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/companies/00000000-0000-7000-8000-000000000000/calculate', server: self::HEADERS, content: '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCalculatingASimpleLine(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/calculate", server: self::HEADERS, content: json_encode([
            'pricing_mode' => 'gross',
            'rounding_method' => 'per_line',
            'date' => '2026-01-01',
            'lines' => [
                ['quantity' => '3', 'unit_price' => '9.99', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
            ],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        /** @var array{lines: list<array{net_amount: string, gross_amount: string, tax_amount: string}>, net_total: string, tax_total: string, gross_total: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('24.37', $body['net_total']);
        self::assertSame('5.60', $body['tax_total']);
        self::assertSame('29.97', $body['gross_total']);
        self::assertCount(1, $body['lines']);
        self::assertSame('29.97', $body['lines'][0]['gross_amount']);
    }

    public function testCalculatingWithDiscountsAndDefaultDate(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/calculate", server: self::HEADERS, content: json_encode([
            'pricing_mode' => 'net',
            'rounding_method' => 'per_line',
            'global_discount_percent' => '10.00',
            'lines' => [
                ['quantity' => '1', 'unit_price' => '100.00', 'tax_region' => 'PT', 'tax_code' => 'RED', 'discounts' => [
                    ['type' => 'percentage', 'value' => '5.00'],
                ]],
            ],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        /** @var array{lines: list<array{settlement_amount: string, discount_amount: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('5.000000', $body['lines'][0]['discount_amount']);
        self::assertSame('9.500000', $body['lines'][0]['settlement_amount']);
    }

    public function testMissingLinesIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/calculate", server: self::HEADERS, content: json_encode([
            'pricing_mode' => 'net',
            'rounding_method' => 'per_line',
            'lines' => [],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnInvalidDecimalIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/calculate", server: self::HEADERS, content: json_encode([
            'pricing_mode' => 'net',
            'rounding_method' => 'per_line',
            'lines' => [
                ['quantity' => 'not-a-number', 'unit_price' => '10.00', 'tax_region' => 'PT', 'tax_code' => 'NOR'],
            ],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/invalid-calculation-line', $body['type']);
    }

    public function testAnUnknownTaxRegionCodeCombinationIsRejectedAsAClientError(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/calculate", server: self::HEADERS, content: json_encode([
            'pricing_mode' => 'net',
            'rounding_method' => 'per_line',
            'lines' => [
                ['quantity' => '1', 'unit_price' => '10.00', 'tax_region' => 'ZZ', 'tax_code' => 'BOGUS'],
            ],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/invalid-calculation-line', $body['type']);
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

    private function registerAndLogIn(KernelBrowser $client, string $email, string $password): UserId
    {
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Flow User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('calculate-%s@example.test', bin2hex(random_bytes(8)));
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
