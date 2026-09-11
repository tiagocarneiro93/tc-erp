<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The task 0.8 acceptance criterion end to end: a seeded user logs in, is
 * forced to change the password, then accesses /me.
 */
final class AuthenticationFlowTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testLoginForcedPasswordChangeThenMe(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $email = $this->uniqueEmail();

        /** @var UserRepository $users */
        $users = $container->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = $container->get(PasswordHasher::class);

        $userId = UserId::generate();
        $user = User::register($userId, $email, 'Flow User', $hasher->hash('temporary-pw'), new \DateTimeImmutable());
        $users->save($user);

        // 1. Login with the temporary password.
        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => 'temporary-pw',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        // 2. Any endpoint other than the allowlist is blocked until the password changes.
        $client->request('GET', '/api/v1/me', server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // 3. Change the password.
        $client->request('POST', '/api/v1/auth/change-password', server: self::HEADERS, content: json_encode([
            'current_password' => 'temporary-pw',
            'new_password' => 'Brand-New-Pw1',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // 4. /me now works.
        $client->request('GET', '/api/v1/me', server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{user: array{email: string, must_change_password: bool}} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($email, $body['user']['email']);
        self::assertFalse($body['user']['must_change_password']);
    }

    public function testChangingToAPasswordThatDoesNotMeetThePolicyIsRejected(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $email = $this->uniqueEmail();

        /** @var UserRepository $users */
        $users = $container->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = $container->get(PasswordHasher::class);

        $users->save(User::register(UserId::generate(), $email, 'Flow User', $hasher->hash('temporary-pw'), new \DateTimeImmutable()));

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => 'temporary-pw',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/v1/auth/change-password', server: self::HEADERS, content: json_encode([
            'current_password' => 'temporary-pw',
            'new_password' => 'all-lowercase-no-digits',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $client = static::createClient();
        $email = $this->uniqueEmail();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $users->save(User::register(UserId::generate(), $email, 'X', $hasher->hash('correct-password'), new \DateTimeImmutable()));

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => 'not-the-password',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testMutatingRequestWithoutCsrfHeaderIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'nobody@example.test',
            'password' => 'whatever',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testLogout(): void
    {
        $client = static::createClient();
        $email = $this->uniqueEmail();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $users->save(User::register(UserId::generate(), $email, 'X', $hasher->hash('pw-12345'), new \DateTimeImmutable()));

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => 'pw-12345',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/v1/auth/logout', server: self::HEADERS);
        self::assertResponseRedirects();

        $client->request('GET', '/api/v1/me', server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    private function uniqueEmail(): string
    {
        return \sprintf('%s@example.test', bin2hex(random_bytes(8)));
    }
}
