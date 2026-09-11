<?php

declare(strict_types=1);

namespace App\Tests\Functional\Fiscal;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-1.md task 1.3: global, read-only reference data, any
 * authenticated user may read it.
 */
final class DocumentTypesControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/document-types');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListsAllTwelveTypes(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/document-types', server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{code: string, saft_section: string, signed: bool, stock_effect: string, account_effect: string, requires_at_prior_communication: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(12, $body['items']);

        $byCode = [];
        foreach ($body['items'] as $item) {
            $byCode[$item['code']] = $item;
        }

        self::assertFalse($byCode['RG']['signed'], 'Receipts are not signed (Despacho 8632/2014 §1.1).');
        self::assertTrue($byCode['FT']['signed']);
        self::assertSame('MovementOfGoods', $byCode['GT']['saft_section']);
        self::assertTrue($byCode['GT']['requires_at_prior_communication']);
        self::assertFalse($byCode['FT']['requires_at_prior_communication']);
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
        return \sprintf('document-types-%s@example.test', bin2hex(random_bytes(6)));
    }
}
