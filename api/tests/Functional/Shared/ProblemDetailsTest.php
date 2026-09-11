<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * CLAUDE.md task 0.11: every /api/v1 error is RFC 9457 problem+json with a
 * stable `type`, whether it comes from a domain exception or from
 * `#[MapRequestPayload]`'s own validation.
 */
final class ProblemDetailsTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testADomainExceptionIsReportedWithItsStableTypeCode(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);

        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => '12345678',
            'legal_name' => 'Whatever Lda',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('application/problem+json', $client->getResponse()->headers->get('Content-Type'));
        /** @var array{type: string, title: string, status: int} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/nif-invalid', $body['type']);
        self::assertSame(422, $body['status']);
    }

    public function testAMapRequestPayloadValidationFailureListsTheOffendingFields(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client);

        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => '',
            'legal_name' => '',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('application/problem+json', $client->getResponse()->headers->get('Content-Type'));
        /** @var array{type: string, errors: list<array{field: string, message: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('https://tc-erp.example/problems/validation-failed', $body['type']);
        $fields = array_column($body['errors'], 'field');
        self::assertContains('nif', $fields);
        self::assertContains('legal_name', $fields);
    }

    private function registerAndLogIn(KernelBrowser $client): void
    {
        $email = \sprintf('%s@example.test', bin2hex(random_bytes(8)));
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Flow User', $hasher->hash('a-password'), new \DateTimeImmutable());
        $user->changePassword($hasher->hash('a-password'), new \DateTimeImmutable());
        $users->save($user);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => 'a-password',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }
}
