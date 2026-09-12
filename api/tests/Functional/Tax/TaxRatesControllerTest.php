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
final class TaxRatesControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/tax-rates');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListsAllRatesByDefault(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/tax-rates', server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{region: string, code: string, percentage: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        // 9 RED/INT/NOR rows (task 1.2) + 3 ISE (exempt) rows, one per region.
        self::assertCount(12, $body['items']);
    }

    public function testFiltersByRegion(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/tax-rates?region=PT', server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<array{region: string, code: string, percentage: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(4, $body['items']);

        $byCode = [];
        foreach ($body['items'] as $item) {
            self::assertSame('PT', $item['region']);
            $byCode[$item['code']] = $item['percentage'];
        }
        self::assertSame('23.00', $byCode['NOR']);
        self::assertSame('13.00', $byCode['INT']);
        self::assertSame('6.00', $byCode['RED']);
        self::assertSame('0.00', $byCode['ISE']);
    }

    public function testFiltersByAsOfDate(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/tax-rates?region=PT&as_of=2000-01-01', server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{items: list<mixed>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(0, $body['items'], 'The mainland rates only became valid from 2011-01-01.');
    }

    public function testRejectsAnInvalidAsOfDate(): void
    {
        $client = static::createClient();
        $this->logIn($client, $this->uniqueEmail(), 'a-password');

        $client->request('GET', '/api/v1/tax-rates?as_of=not-a-date', server: self::HEADERS);
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
        return \sprintf('tax-rates-%s@example.test', bin2hex(random_bytes(6)));
    }
}
