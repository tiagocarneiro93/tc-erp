<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\Domain\AtCredentialsRepository;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.4: the "test" endpoint only round-trips this
 * app's own encryption and checks the subuser format — it never reaches AT.
 */
final class AtCredentialsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testUpdatingRequiresAuthentication(): void
    {
        $client = static::createClient();
        // The CSRF header check (priority 40) runs before the firewall
        // (priority 8) for every unsafe method on /api/v1, so it must be
        // present here too, or a PUT without it always gets 403 first
        // regardless of authentication (App\Shared\Infrastructure\Http\CsrfHeaderListener).
        $client->request('PUT', '/api/v1/companies/00000000-0000-7000-8000-000000000000/at-credentials', server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAnOwnerCanStoreCredentialsAndTheyAreEncryptedAtRest(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('PUT', "/api/v1/companies/{$companyId}/at-credentials", server: self::HEADERS, content: json_encode([
            'subuser' => '555555555/55',
            'password' => 'a-plaintext-at-password',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var AtCredentialsRepository $credentials */
        $credentials = static::getContainer()->get(AtCredentialsRepository::class);
        $stored = $credentials->find(CompanyId::fromString($companyId));
        self::assertNotNull($stored);
        self::assertSame('555555555/55', $stored->subuser());
        self::assertStringNotContainsString('a-plaintext-at-password', $stored->passwordEncrypted());
    }

    public function testUpdatingWithAnInvalidSubuserFormatIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('PUT', "/api/v1/companies/{$companyId}/at-credentials", server: self::HEADERS, content: json_encode([
            'subuser' => 'not-the-right-shape',
            'password' => 'a-plaintext-at-password',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testTestingWithNoCredentialsConfiguredIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/at-credentials/test", server: self::HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTestingValidStoredCredentialsReportsSuccess(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('PUT', "/api/v1/companies/{$companyId}/at-credentials", server: self::HEADERS, content: json_encode([
            'subuser' => '555555555/55',
            'password' => 'a-plaintext-at-password',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('POST', "/api/v1/companies/{$companyId}/at-credentials/test", server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{valid: bool, checked_at: ?string, error: ?string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertTrue($body['valid']);
        self::assertNotNull($body['checked_at']);
        self::assertNull($body['error']);
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
        return \sprintf('at-credentials-%s@example.test', bin2hex(random_bytes(8)));
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
