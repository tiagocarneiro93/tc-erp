<?php

declare(strict_types=1);

namespace App\Tests\Functional\Parties;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.5: "Consumidor final" is seeded as soon as
 * the company exists (docs/decisions/0004's `CompanyRegistered` event,
 * same pattern as task 1.4's default company profile); full CRUD + search
 * for everything else.
 */
final class CustomersControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/customers');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testANewCompanyAlreadyHasTheFinalConsumerCustomer(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{code: string, nif: string, name: string, is_final_consumer: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame('999999990', $body['items'][0]['nif']);
        self::assertSame('Consumidor final', $body['items'][0]['name']);
        self::assertTrue($body['items'][0]['is_final_consumer']);
    }

    public function testTheFinalConsumerCustomerCannotBeUpdatedOrDeactivated(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS);
        /** @var array{items: list<array{id: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $finalConsumerId = $body['items'][0]['id'];

        $client->request('PUT', "/api/v1/companies/{$companyId}/customers/{$finalConsumerId}", server: self::HEADERS, content: json_encode([
            'code' => 'CF',
            'nif' => '999999990',
            'name' => 'Renamed',
            'country' => 'PT',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $updateBody */
        $updateBody = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/final-consumer-customer-is-protected', $updateBody['type']);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/customers/{$finalConsumerId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $deactivateBody */
        $deactivateBody = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/final-consumer-customer-is-protected', $deactivateBody['type']);
    }

    public function testCreatingReadingUpdatingAndDeactivatingACustomer(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $nif = $this->uniqueNif();

        $client->request('POST', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS, content: json_encode([
            'code' => 'C001',
            'nif' => $nif,
            'name' => 'Cliente Um',
            'country' => 'PT',
            'email' => 'cliente@example.test',
            'payment_terms_id' => $this->aKnownPaymentTermsId($client, $companyId),
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $customerId = $created['id'];

        $client->request('GET', "/api/v1/companies/{$companyId}/customers/{$customerId}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{code: string, name: string, active: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('C001', $body['code']);
        self::assertSame('Cliente Um', $body['name']);
        self::assertTrue($body['active']);

        $client->request('PUT', "/api/v1/companies/{$companyId}/customers/{$customerId}", server: self::HEADERS, content: json_encode([
            'code' => 'C001',
            'nif' => $nif,
            'name' => 'Cliente Um Atualizado',
            'country' => 'PT',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/customers/{$customerId}", server: self::HEADERS);
        /** @var array{name: string} $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Cliente Um Atualizado', $updated['name']);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/customers/{$customerId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/customers/{$customerId}", server: self::HEADERS);
        /** @var array{active: bool} $deactivated */
        $deactivated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($deactivated['active'], 'Deactivating is a soft delete (active=false), not a hard DELETE.');
    }

    public function testCreatingWithAnInvalidDomesticNifIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS, content: json_encode([
            'code' => 'C002',
            'nif' => '111111111',
            'name' => 'Cliente Inválido',
            'country' => 'PT',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testAForeignCustomersNifIsFreeTextNotCheckDigitValidated(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS, content: json_encode([
            'code' => 'C003',
            'nif' => 'FR-VAT-INVALID-SHAPE',
            'name' => 'Client Étranger',
            'country' => 'FR',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testCreatingWithAnUnknownCountryCodeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS, content: json_encode([
            'code' => 'C004',
            'nif' => $this->uniqueNif(),
            'name' => 'Cliente',
            'country' => 'ZZ',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreatingWithAnUnknownPaymentTermsIdIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS, content: json_encode([
            'code' => 'C005',
            'nif' => $this->uniqueNif(),
            'name' => 'Cliente',
            'country' => 'PT',
            'payment_terms_id' => '00000000-0000-7000-8000-000000000000',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        /** @var array{type: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/invalid-payment-terms-id', $body['type']);
    }

    public function testSearchingFiltersByCodeNifOrName(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/customers", server: self::HEADERS, content: json_encode([
            'code' => 'ACME01',
            'nif' => $this->uniqueNif(),
            'name' => 'Acme Trading',
            'country' => 'PT',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->request('GET', "/api/v1/companies/{$companyId}/customers?search=Acme", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{name: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);
        self::assertSame('Acme Trading', $body['items'][0]['name']);

        $client->request('GET', "/api/v1/companies/{$companyId}/customers?search=NoSuchThing", server: self::HEADERS);
        /** @var array{items: list<mixed>} $noMatch */
        $noMatch = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(0, $noMatch['items']);
    }

    public function testGettingAnUnknownCustomerIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/customers/00000000-0000-7000-8000-000000000000", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
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

    private function aKnownPaymentTermsId(KernelBrowser $client, string $companyId): string
    {
        $client->request('GET', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{id: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['items'], 'Expected the default "Pronto Pagamento" payment terms seeded on company creation to exist.');

        return $body['items'][0]['id'];
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
        return \sprintf('customers-%s@example.test', bin2hex(random_bytes(8)));
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
