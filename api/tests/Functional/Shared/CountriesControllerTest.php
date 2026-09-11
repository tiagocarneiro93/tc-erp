<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.1: global, read-only reference data, any
 * authenticated user may read it.
 */
final class CountriesControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/countries');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAuthenticatedUserCanListCountries(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/countries', server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{code: string, name: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $codes = array_column($body['items'], 'code');

        self::assertContains('PT', $codes);
        self::assertGreaterThan(100, \count($body['items']));
    }

    private function logIn(KernelBrowser $client, string $email, string $password): void
    {
        $users = static::getContainer()->get(UserRepository::class);
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Flow User', $hasher->hash($password), new \DateTimeImmutable());
        $user->changePassword($hasher->hash($password), new \DateTimeImmutable());
        $users->save($user);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }

    private function uniqueEmail(): string
    {
        return \sprintf('countries-%s@example.test', bin2hex(random_bytes(6)));
    }
}
