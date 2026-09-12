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
 * docs/plans/phase-1.md task 1.8: nesting is rejected outright with a
 * stable problem+json type code; the VAT-rate-mismatch warning surfaces
 * without blocking the write; upsert/removal behave like every other
 * composite-key resource in this module.
 */
final class ProductComponentsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testSettingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/api/v1/companies/00000000-0000-7000-8000-000000000000/products/00000000-0000-7000-8000-000000000000/components/00000000-0000-7000-8000-000000000000', server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAddingUpdatingAndRemovingAComponent(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->normalRateId();
        $kitId = $this->createProduct($client, $companyId, 'kit', $taxRateId);
        $componentId = $this->createProduct($client, $companyId, 'simple', $taxRateId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$componentId}", server: self::HEADERS, content: json_encode([
            'quantity' => '2',
            'sort_order' => 1,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$kitId}/components", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{component_product_id: string, quantity: string, sort_order: int, vat_rate_mismatch: bool}>, estimated_cost: ?string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame($componentId, $body['items'][0]['component_product_id']);
        self::assertSame('2.000000', $body['items'][0]['quantity']);
        self::assertSame(1, $body['items'][0]['sort_order']);
        self::assertFalse($body['items'][0]['vat_rate_mismatch']);
        self::assertNull($body['estimated_cost'], 'average_cost is never populated in Phase 1 (Phase 5 gap, not a bug).');

        // Setting it again is an upsert, not a duplicate row.
        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$componentId}", server: self::HEADERS, content: json_encode([
            'quantity' => '5',
            'sort_order' => 0,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$kitId}/components", server: self::HEADERS);
        /** @var array{items: list<array{quantity: string, sort_order: int}>} $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $updated['items']);
        self::assertSame('5.000000', $updated['items'][0]['quantity']);
        self::assertSame(0, $updated['items'][0]['sort_order']);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$componentId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$kitId}/components", server: self::HEADERS);
        /** @var array{items: list<mixed>} $afterRemoval */
        $afterRemoval = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(0, $afterRemoval['items']);
    }

    public function testAddingANestedKitIsRejectedWithAStableProblemType(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->normalRateId();
        $kitId = $this->createProduct($client, $companyId, 'kit', $taxRateId);
        $innerKitId = $this->createProduct($client, $companyId, 'kit', $taxRateId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$innerKitId}", server: self::HEADERS, content: json_encode([
            'quantity' => '1',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/nested-kit-not-allowed', $body['type']);
    }

    public function testAddingComponentsToANonKitProductIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->normalRateId();
        $notAKit = $this->createProduct($client, $companyId, 'simple', $taxRateId);
        $componentId = $this->createProduct($client, $companyId, 'simple', $taxRateId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$notAKit}/components/{$componentId}", server: self::HEADERS, content: json_encode([
            'quantity' => '1',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/not-a-kit', $body['type']);
    }

    public function testVatRateMismatchIsInformationalAndDoesNotBlockTheWrite(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $normalRateId = $this->normalRateId();
        $reducedRateId = $this->reducedRateId();
        $kitId = $this->createProduct($client, $companyId, 'kit', $normalRateId);
        $componentId = $this->createProduct($client, $companyId, 'simple', $reducedRateId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$componentId}", server: self::HEADERS, content: json_encode([
            'quantity' => '1',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT, 'A VAT-rate mismatch is informational-only; it must never block the write.');

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$kitId}/components", server: self::HEADERS);
        /** @var array{items: list<array{vat_rate_mismatch: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertTrue($body['items'][0]['vat_rate_mismatch']);
    }

    public function testSettingAnInvalidQuantityIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->normalRateId();
        $kitId = $this->createProduct($client, $companyId, 'kit', $taxRateId);
        $componentId = $this->createProduct($client, $companyId, 'simple', $taxRateId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$componentId}", server: self::HEADERS, content: json_encode([
            'quantity' => '0',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$componentId}", server: self::HEADERS, content: json_encode([
            'quantity' => 'not-a-number',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
    }

    public function testSettingAComponentOnAnUnknownProductIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->normalRateId();
        $kitId = $this->createProduct($client, $companyId, 'kit', $taxRateId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$kitId}/components/00000000-0000-7000-8000-000000000000", server: self::HEADERS, content: json_encode([
            'quantity' => '1',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRemovingAnUnknownComponentIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->normalRateId();
        $kitId = $this->createProduct($client, $companyId, 'kit', $taxRateId);
        $componentId = $this->createProduct($client, $companyId, 'simple', $taxRateId);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/products/{$kitId}/components/{$componentId}", server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function normalRateId(): string
    {
        return $this->rateIdByCode('NOR');
    }

    private function reducedRateId(): string
    {
        return $this->rateIdByCode('RED');
    }

    private function rateIdByCode(string $code): string
    {
        /** @var TaxRateRepository $taxRates */
        $taxRates = static::getContainer()->get(TaxRateRepository::class);
        foreach ($taxRates->findAll('PT', null) as $rate) {
            if ($code === $rate->code()) {
                return $rate->id()->toString();
            }
        }

        self::fail(\sprintf('Expected the PT/%s tax rate seeded by task 1.2 to exist.', $code));
    }

    private function createProduct(KernelBrowser $client, string $companyId, string $kind, string $taxRateId): string
    {
        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P-'.bin2hex(random_bytes(4)),
            'description' => 'Test product',
            'type' => 'P',
            'kind' => $kind,
            'unit_code' => 'UN',
            'tax_rate_id' => $taxRateId,
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
        return \sprintf('product-components-%s@example.test', bin2hex(random_bytes(8)));
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
