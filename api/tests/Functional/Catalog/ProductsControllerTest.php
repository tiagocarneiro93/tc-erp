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
 * docs/plans/phase-1.md task 1.6: full CRUD (deactivate, not hard delete),
 * cursor-paginated search/filter.
 */
final class ProductsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/products');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreatingReadingUpdatingAndDeactivatingAProduct(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->aKnownTaxRateId();

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P001',
            'description' => 'Vinho Tinto',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => $taxRateId,
            'track_stock' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $productId = $created['id'];

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$productId}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{code: string, description: string, kind: string, active: bool, last_cost: ?string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('P001', $body['code']);
        self::assertSame('simple', $body['kind']);
        self::assertTrue($body['active']);
        self::assertNull($body['last_cost'], 'last_cost is unused until Phase 5.');

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$productId}", server: self::HEADERS, content: json_encode([
            'code' => 'P001',
            'description' => 'Vinho Tinto Reserva',
            'type' => 'P',
            'unit_code' => 'UN',
            'tax_rate_id' => $taxRateId,
            'track_stock' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$productId}", server: self::HEADERS);
        /** @var array{description: string} $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Vinho Tinto Reserva', $updated['description']);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/products/{$productId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/{$productId}", server: self::HEADERS);
        /** @var array{active: bool} $deactivated */
        $deactivated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($deactivated['active'], 'Deactivating is a soft delete (active=false), not a hard DELETE.');
    }

    public function testCreatingWithAnInvalidTypeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P002',
            'description' => 'Invalid Type',
            'type' => 'X',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->aKnownTaxRateId(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingWithAnInvalidKindIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P003',
            'description' => 'Invalid Kind',
            'type' => 'P',
            'kind' => 'assembled',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->aKnownTaxRateId(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingAKitProductIsStructurallyAllowed(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'KIT01',
            'description' => 'Cabaz de Natal',
            'type' => 'P',
            'kind' => 'kit',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->aKnownTaxRateId(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testCreatingWithAnUnknownUnitCodeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P004',
            'description' => 'Unknown Unit',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'ZZZ',
            'tax_rate_id' => $this->aKnownTaxRateId(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingWithAnUnknownTaxRateIdIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P005',
            'description' => 'Unknown Tax Rate',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => '00000000-0000-7000-8000-000000000000',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingWithAnUnknownExemptionReasonCodeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P006',
            'description' => 'Unknown Exemption',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->aKnownTaxRateId(),
            'exemption_reason_code' => 'M99999',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingWithTheExemptRateAndNoExemptionReasonIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P007',
            'description' => 'Exempt, no reason',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->anExemptTaxRateId(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/exemption-reason-required', $body['type']);
    }

    public function testCreatingWithTheExemptRateAndAnExemptionReasonSucceeds(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P008',
            'description' => 'Exempt, with reason',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->anExemptTaxRateId(),
            'exemption_reason_code' => 'M04',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testUpdatingToTheExemptRateWithoutAnExemptionReasonIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'P009',
            'description' => 'Normal, then exempt',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->aKnownTaxRateId(),
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('PUT', "/api/v1/companies/{$companyId}/products/{$created['id']}", server: self::HEADERS, content: json_encode([
            'code' => 'P009',
            'description' => 'Normal, then exempt',
            'type' => 'P',
            'unit_code' => 'UN',
            'tax_rate_id' => $this->anExemptTaxRateId(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testFilteringByFamilyActiveAndTrackStock(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $taxRateId = $this->aKnownTaxRateId();

        $client->request('POST', "/api/v1/companies/{$companyId}/product-families", server: self::HEADERS, content: json_encode([
            'name' => 'Bebidas',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $family */
        $family = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'BEB01',
            'description' => 'Água',
            'type' => 'P',
            'kind' => 'simple',
            'unit_code' => 'UN',
            'family_id' => $family['id'],
            'tax_rate_id' => $taxRateId,
            'track_stock' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('POST', "/api/v1/companies/{$companyId}/products", server: self::HEADERS, content: json_encode([
            'code' => 'SRV01',
            'description' => 'Serviço de consultoria',
            'type' => 'S',
            'kind' => 'simple',
            'unit_code' => 'H',
            'tax_rate_id' => $taxRateId,
            'track_stock' => false,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('GET', "/api/v1/companies/{$companyId}/products?family_id={$family['id']}", server: self::HEADERS);
        /** @var array{items: list<array{code: string}>} $byFamily */
        $byFamily = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $byFamily['items']);
        self::assertSame('BEB01', $byFamily['items'][0]['code']);

        $client->request('GET', "/api/v1/companies/{$companyId}/products?track_stock=true", server: self::HEADERS);
        /** @var array{items: list<array{code: string}>} $tracked */
        $tracked = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $tracked['items']);
        self::assertSame('BEB01', $tracked['items'][0]['code']);

        $client->request('GET', "/api/v1/companies/{$companyId}/products?search=consultoria", server: self::HEADERS);
        /** @var array{items: list<array{code: string}>} $searched */
        $searched = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $searched['items']);
        self::assertSame('SRV01', $searched['items'][0]['code']);
    }

    public function testGettingAnUnknownProductIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/products/00000000-0000-7000-8000-000000000000", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function aKnownTaxRateId(): string
    {
        /** @var TaxRateRepository $taxRates */
        $taxRates = static::getContainer()->get(TaxRateRepository::class);
        $rates = $taxRates->findAll('PT', null);
        self::assertNotEmpty($rates, 'Expected the PT tax rates seeded by task 1.2 to exist.');

        return $rates[0]->id()->toString();
    }

    private function anExemptTaxRateId(): string
    {
        /** @var TaxRateRepository $taxRates */
        $taxRates = static::getContainer()->get(TaxRateRepository::class);
        foreach ($taxRates->findAll('PT', null) as $rate) {
            if ('ISE' === $rate->code()) {
                return $rate->id()->toString();
            }
        }

        self::fail('Expected the PT/ISE (0%) exempt tax rate to exist.');
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
        return \sprintf('products-%s@example.test', bin2hex(random_bytes(8)));
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
