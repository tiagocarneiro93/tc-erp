<?php

declare(strict_types=1);

namespace App\Tests\Functional\Tax;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.2: global, read-only reference data, any
 * authenticated user may read it.
 */
final class ExemptionReasonsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/exemption-reasons');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListsTheFullOfficialTable(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/exemption-reasons', server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{code: string, description: string, legal_reference: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(33, $body['items']);

        $byCode = [];
        foreach ($body['items'] as $item) {
            $byCode[$item['code']] = $item;
        }

        self::assertArrayHasKey('M99', $byCode);
        self::assertSame('Não sujeito ou não tributado', $byCode['M99']['description']);
        self::assertArrayHasKey('M35', $byCode);
        self::assertStringContainsString('Verba 2.42 da Lista I', $byCode['M35']['legal_reference']);
    }

    public function testFiltersByAsOfDateExcludesLaterAdditions(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/exemption-reasons?as_of=2022-08-01', server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{code: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $codes = array_column($body['items'], 'code');

        self::assertContains('M01', $codes);
        self::assertNotContains('M26', $codes, 'M26 was only added in V2.0 (2023-04-14).');
        self::assertNotContains('M35', $codes, 'M35 only applies to invoices from 2026-07-01.');
    }

    public function testRejectsAnInvalidAsOfDate(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/exemption-reasons?as_of=not-a-date', server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
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
        return \sprintf('exemption-reasons-%s@example.test', bin2hex(random_bytes(6)));
    }
}
