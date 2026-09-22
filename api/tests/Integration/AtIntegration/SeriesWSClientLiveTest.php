<?php

declare(strict_types=1);

namespace App\Tests\Integration\AtIntegration;

use App\AtIntegration\Infrastructure\Soap\SeriesWSClient;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\AtIntegration\SeriesRegistration;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/phase-3.md task 3.1, decision 10: "the real proof is a live
 * call against AT's test environment... as each task's acceptance
 * criterion, not simulated." Everyday tests use
 * {@see \App\AtIntegration\Infrastructure\Fake\FakeSeriesWebserviceClient}
 * (`config/services_test.yaml`); this test is the one place that talks to
 * the genuine `registarSerie` operation, using the container's real
 * `SeriesWSClient` binding (`public: true` in `services.yaml` specifically
 * for this purpose, bypassing the Fake alias).
 *
 * Skipped unless real AT sandbox credentials are supplied out-of-band —
 * per the owner's own instruction, real values belong in
 * `.env.test.local` (never `.env.local`, which Symfony's Dotenv ignores
 * under APP_ENV=test), so this never blocks a sandbox without them.
 *
 * Also the only place that can confirm or refute this codebase's one
 * unverified assumption: {@see \App\AtIntegration\Infrastructure\Security\OpenSslAtRequestCipher}'s
 * RSA padding (`OPENSSL_PKCS1_PADDING`), which neither AT manual states
 * explicitly (CLAUDE.md "never guess an AT technical format without
 * saying so").
 */
final class SeriesWSClientLiveTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testRegisteringASeriesAgainstTheRealAtTestEnvironment(): void
    {
        $subuser = getenv('AT_TEST_SUBUSER');
        $password = getenv('AT_TEST_PASSWORD');

        if (!\is_string($subuser) || '' === $subuser || !\is_string($password) || '' === $password) {
            self::markTestSkipped('AT_TEST_SUBUSER / AT_TEST_PASSWORD not set — set them in api/.env.test.local to run this test against AT\'s real sandbox.');
        }

        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('PUT', "/api/v1/companies/{$companyId}/at-credentials", server: self::HEADERS, content: json_encode([
            'subuser' => $subuser,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var SeriesWSClient $seriesClient */
        $seriesClient = static::getContainer()->get(SeriesWSClient::class);

        $code = 'LIVE'.substr(bin2hex(random_bytes(4)), 0, 4);
        $registration = new SeriesRegistration(
            code: $code,
            isTraining: true,
            documentType: 'FT',
            startNumber: 1,
            expectedStartDate: new \DateTimeImmutable('today'),
        );

        $result = $seriesClient->register(CompanyId::fromString($companyId), $registration);

        self::assertTrue($result->accepted, \sprintf('AT rejected registarSerie: [%d] %s', $result->responseCode, $result->responseMessage));
        self::assertNotNull($result->validationCode);
        self::assertNotSame('', $result->validationCode);
    }

    private function createCompany(KernelBrowser $client, string $legalName = 'A Live AT Test Company Lda'): string
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

        $user = User::register(UserId::generate(), $email, 'Live AT Test User', $hasher->hash($password), new \DateTimeImmutable());
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
        return \sprintf('at-live-%s@example.test', bin2hex(random_bytes(8)));
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
