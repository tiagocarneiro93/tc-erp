<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inventory;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.9: a new company gets exactly one default
 * warehouse (decision 4, seeded by `CreateDefaultWarehouseOnCompanyRegistered`
 * on `CompanyRegistered`); full CRUD; marking a different warehouse default
 * unmarks the previous one, keeping "exactly one default" true over time.
 */
final class WarehousesControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/warehouses');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testANewCompanyGetsExactlyOneDefaultWarehouse(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{code: string, name: string, is_default: bool, active: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertCount(1, $body['items']);
        self::assertTrue($body['items'][0]['is_default']);
        self::assertTrue($body['items'][0]['active']);
    }

    public function testCreatingReadingUpdatingAndDeactivatingAWarehouse(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/warehouses", server: self::HEADERS, content: json_encode([
            'code' => 'ARM2',
            'name' => 'Armazém secundário',
            'address' => 'Rua das Flores, 10',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $warehouseId = $created['id'];

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses/{$warehouseId}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{code: string, name: string, address: ?string, is_default: bool, active: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('ARM2', $body['code']);
        self::assertSame('Armazém secundário', $body['name']);
        self::assertSame('Rua das Flores, 10', $body['address']);
        self::assertFalse($body['is_default']);
        self::assertTrue($body['active']);

        $client->request('PUT', "/api/v1/companies/{$companyId}/warehouses/{$warehouseId}", server: self::HEADERS, content: json_encode([
            'code' => 'ARM2B',
            'name' => 'Armazém secundário (renomeado)',
            'address' => null,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses/{$warehouseId}", server: self::HEADERS);
        /** @var array{code: string, name: string, address: ?string} $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('ARM2B', $updated['code']);
        self::assertSame('Armazém secundário (renomeado)', $updated['name']);
        self::assertNull($updated['address']);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/warehouses/{$warehouseId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses/{$warehouseId}", server: self::HEADERS);
        /** @var array{active: bool} $afterDeactivation */
        $afterDeactivation = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($afterDeactivation['active'], 'Deactivation is active=false, not a hard delete.');
    }

    public function testMarkingADifferentWarehouseAsDefaultUnmarksThePreviousOne(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $originalDefaultId = $this->onlyWarehouseId($client, $companyId);

        $client->request('POST', "/api/v1/companies/{$companyId}/warehouses", server: self::HEADERS, content: json_encode([
            'code' => 'ARM2',
            'name' => 'Armazém secundário',
            'is_default' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $newWarehouseId = $created['id'];

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses", server: self::HEADERS);
        /** @var array{items: list<array{id: string, is_default: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $defaults = array_values(array_filter($body['items'], static fn (array $w) => $w['is_default']));

        self::assertCount(1, $defaults, 'Exactly one warehouse must remain default.');
        self::assertSame($newWarehouseId, $defaults[0]['id']);

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses/{$originalDefaultId}", server: self::HEADERS);
        /** @var array{is_default: bool} $original */
        $original = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($original['is_default']);
    }

    public function testUpdatingAWarehouseToDefaultUnmarksThePreviousOne(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $originalDefaultId = $this->onlyWarehouseId($client, $companyId);

        $client->request('POST', "/api/v1/companies/{$companyId}/warehouses", server: self::HEADERS, content: json_encode([
            'code' => 'ARM2',
            'name' => 'Armazém secundário',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $newWarehouseId = $created['id'];

        $client->request('PUT', "/api/v1/companies/{$companyId}/warehouses/{$newWarehouseId}", server: self::HEADERS, content: json_encode([
            'code' => 'ARM2',
            'name' => 'Armazém secundário',
            'is_default' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses/{$originalDefaultId}", server: self::HEADERS);
        /** @var array{is_default: bool} $original */
        $original = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($original['is_default']);

        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses/{$newWarehouseId}", server: self::HEADERS);
        /** @var array{is_default: bool} $new */
        $new = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertTrue($new['is_default']);
    }

    public function testUpdatingAnUnknownWarehouseIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('PUT', "/api/v1/companies/{$companyId}/warehouses/00000000-0000-7000-8000-000000000000", server: self::HEADERS, content: json_encode([
            'code' => 'X',
            'name' => 'X',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function onlyWarehouseId(KernelBrowser $client, string $companyId): string
    {
        $client->request('GET', "/api/v1/companies/{$companyId}/warehouses", server: self::HEADERS);
        /** @var array{items: list<array{id: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);

        return $body['items'][0]['id'];
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
        return \sprintf('warehouses-%s@example.test', bin2hex(random_bytes(8)));
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
