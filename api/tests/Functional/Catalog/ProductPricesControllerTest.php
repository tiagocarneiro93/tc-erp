<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use App\Tax\Domain\TaxRateRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.7: prices round-trip exactly as entered;
 * the conversion endpoint matches technical-scope.md §7.9.8's own worked
 * example (9,99 / 1,23 = 8,121951).
 */
final class ProductPricesControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testSettingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/api/v1/companies/00000000-0000-7000-8000-000000000000/products/00000000-0000-7000-8000-000000000000/prices/00000000-0000-7000-8000-000000000000', server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testPricesRoundTripExactlyAsEntered(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $productId = $this->createProduct($client, $companyId, $this->normalRateId());
        $priceListId = $this->createPriceList($client, $companyId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$productId}/prices/{$priceListId}", server: self::HEADERS, content: json_encode([
            'amount' => '9.99',
            'includes_vat' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$productId}/prices", server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{price_list_id: string, amount: string, includes_vat: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame($priceListId, $body['items'][0]['price_list_id']);
        self::assertSame('9.990000', $body['items'][0]['amount'], 'Stored at the column\'s NUMERIC(19,6) precision, not re-rounded.');
        self::assertTrue($body['items'][0]['includes_vat']);

        // Setting it again is an upsert, not a duplicate.
        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$productId}/prices/{$priceListId}", server: self::HEADERS, content: json_encode([
            'amount' => '12.50',
            'includes_vat' => false,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$productId}/prices", server: self::HEADERS);
        /** @var array{items: list<array{amount: string, includes_vat: bool}>} $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $updated['items']);
        self::assertSame('12.500000', $updated['items'][0]['amount']);
        self::assertFalse($updated['items'][0]['includes_vat']);
    }

    public function testSettingAnInvalidAmountIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $productId = $this->createProduct($client, $companyId, $this->normalRateId());
        $priceListId = $this->createPriceList($client, $companyId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$productId}/prices/{$priceListId}", server: self::HEADERS, content: json_encode([
            'amount' => 'not-a-number',
            'includes_vat' => true,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testSettingAPriceForAnUnknownPriceListIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $productId = $this->createProduct($client, $companyId, $this->normalRateId());

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$productId}/prices/00000000-0000-7000-8000-000000000000", server: self::HEADERS, content: json_encode([
            'amount' => '9.99',
            'includes_vat' => true,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCalculateMatchesTheScopesWorkedExample(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $productId = $this->createProduct($client, $companyId, $this->normalRateId());

        $client->request('POST', "/api/v1/companies/{$companyId}/products/{$productId}/prices/calculate", server: self::HEADERS, content: json_encode([
            'amount' => '9.99',
            'includes_vat' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        /** @var array{amount: string, includes_vat: bool, converted_amount: string, converted_includes_vat: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('9.99', $body['amount']);
        self::assertTrue($body['includes_vat']);
        self::assertSame('8.121951', $body['converted_amount']);
        self::assertFalse($body['converted_includes_vat']);
    }

    public function testCalculateIsDisplayOnlyAndNeverPersisted(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $productId = $this->createProduct($client, $companyId, $this->normalRateId());

        $client->request('POST', "/api/v1/companies/{$companyId}/products/{$productId}/prices/calculate", server: self::HEADERS, content: json_encode([
            'amount' => '9.99',
            'includes_vat' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$productId}/prices", server: self::HEADERS);
        /** @var array{items: list<mixed>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['items']);
    }

    public function testCalculatingWithAnInvalidAmountIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $productId = $this->createProduct($client, $companyId, $this->normalRateId());

        $client->request('POST', "/api/v1/companies/{$companyId}/products/{$productId}/prices/calculate", server: self::HEADERS, content: json_encode([
            'amount' => 'garbage',
            'includes_vat' => true,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    private function normalRateId(): string
    {
        /** @var TaxRateRepository $taxRates */
        $taxRates = static::getContainer()->get(TaxRateRepository::class);
        foreach ($taxRates->findAll('PT', null) as $rate) {
            if ('NOR' === $rate->code()) {
                return $rate->id()->toString();
            }
        }

        self::fail('Expected the PT/NOR (23%) tax rate seeded by task 1.2 to exist.');
    }

    private function createProduct(KernelBrowser $client, string $companyId, string $taxRateId): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P001',
            'description' => 'Vinho Tinto',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => $taxRateId,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $created['id'];
    }

    private function createPriceList(KernelBrowser $client, string $companyId): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/price-lists", server: self::HEADERS, content: json_encode([
            'name' => 'Preço de tabela',
            'default_includes_vat' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

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
        return \sprintf('product-prices-%s@example.test', bin2hex(random_bytes(8)));
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
