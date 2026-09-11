<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

final class PasswordResetFlowTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testRequestAndConfirmResetThenLoginWithNewPassword(): void
    {
        $client = static::createClient();
        $userEmail = $this->uniqueEmail();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $users->save(User::register(UserId::generate(), $userEmail, 'Reset Me', $hasher->hash('old-password'), new \DateTimeImmutable()));

        $client->request('POST', '/api/v1/auth/password/reset', server: self::HEADERS, content: json_encode([
            'email' => $userEmail,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $sentEmail = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $sentEmail);
        self::assertEmailTextBodyContains($sentEmail, 'reset-password?token=');

        $textBody = $sentEmail->getTextBody();
        $body = match (true) {
            \is_string($textBody) => $textBody,
            null === $textBody => '',
            default => (string) stream_get_contents($textBody),
        };
        preg_match('/token=([a-f0-9]+)/', $body, $matches);

        if (!isset($matches[1])) {
            self::fail('Could not find the reset token in the email body.');
        }

        $token = $matches[1];

        $client->request('POST', '/api/v1/auth/password/reset/confirm', server: self::HEADERS, content: json_encode([
            'token' => $token,
            'new_password' => 'a-completely-new-password',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The old password no longer works, the new one does.
        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $userEmail,
            'password' => 'old-password',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $userEmail,
            'password' => 'a-completely-new-password',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }

    public function testRequestingResetForAnUnknownEmailStillReturnsAccepted(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/password/reset', server: self::HEADERS, content: json_encode([
            'email' => 'nobody-at-all@example.test',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
    }

    public function testConfirmingWithAnInvalidTokenIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/password/reset/confirm', server: self::HEADERS, content: json_encode([
            'token' => 'not-a-real-token',
            'new_password' => 'whatever-new',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    private function uniqueEmail(): string
    {
        return \sprintf('%s@example.test', bin2hex(random_bytes(8)));
    }
}
