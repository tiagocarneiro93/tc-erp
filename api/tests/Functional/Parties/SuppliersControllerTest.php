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

final class SuppliersControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/suppliers');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testANewCompanyHasNoSuppliersYet(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/suppliers", server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<mixed>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['items'], 'Unlike customers, no default supplier is seeded.');
    }

    public function testCreatingReadingUpdatingAndDeactivatingASupplier(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $nif = $this->uniqueNif();

        $client->request('POST', "/api/v1/companies/{$companyId}/suppliers", server: self::HEADERS, content: json_encode([
            'code' => 'F001',
            'nif' => $nif,
            'name' => 'Fornecedor Um',
            'country' => 'PT',
            'payment_terms_days' => 60,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $supplierId = $created['id'];

        $client->request('GET', "/api/v1/companies/{$companyId}/suppliers/{$supplierId}", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{code: string, name: string, active: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('F001', $body['code']);
        self::assertTrue($body['active']);

        $client->request('PUT', "/api/v1/companies/{$companyId}/suppliers/{$supplierId}", server: self::HEADERS, content: json_encode([
            'code' => 'F001',
            'nif' => $nif,
            'name' => 'Fornecedor Um Atualizado',
            'country' => 'PT',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/suppliers/{$supplierId}", server: self::HEADERS);
        /** @var array{name: string} $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Fornecedor Um Atualizado', $updated['name']);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/suppliers/{$supplierId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/suppliers/{$supplierId}", server: self::HEADERS);
        /** @var array{active: bool} $deactivated */
        $deactivated = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertFalse($deactivated['active']);
    }

    public function testCreatingWithAnInvalidDomesticNifIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/suppliers", server: self::HEADERS, content: json_encode([
            'code' => 'F002',
            'nif' => '111111111',
            'name' => 'Fornecedor Inválido',
            'country' => 'PT',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testGettingAnUnknownSupplierIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/suppliers/00000000-0000-7000-8000-000000000000", server: self::HEADERS);
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
        return \sprintf('suppliers-%s@example.test', bin2hex(random_bytes(8)));
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
