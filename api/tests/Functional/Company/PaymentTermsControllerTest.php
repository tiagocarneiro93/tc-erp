<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * A new company gets exactly one seeded payment term, "Pronto Pagamento" (0
 * dias), marked default (seeded by `CreateDefaultPaymentTermsOnCompanyRegistered`
 * on `CompanyRegistered`); full CRUD; marking a different payment terms
 * default unmarks the previous one, keeping "exactly one default" true over
 * time.
 */
final class PaymentTermsControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testListingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/payment-terms');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testANewCompanyGetsExactlyOneDefaultPaymentTerms(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('GET', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{name: string, days: int, is_default: bool, active: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertCount(1, $body['items']);
        self::assertSame('Pronto Pagamento', $body['items'][0]['name']);
        self::assertSame(0, $body['items'][0]['days']);
        self::assertTrue($body['items'][0]['is_default']);
        self::assertTrue($body['items'][0]['active']);
    }

    public function testCreatingReadingUpdatingAndDeactivatingPaymentTerms(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('POST', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS, content: json_encode([
            'name' => '30 dias',
            'days' => 30,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $paymentTermsId = $created['id'];

        $client->request('GET', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS);
        /** @var array{items: list<array{id: string, name: string, days: int, is_default: bool, active: bool}>} $listed */
        $listed = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $byId = array_column($listed['items'], null, 'id');
        self::assertSame('30 dias', $byId[$paymentTermsId]['name']);
        self::assertSame(30, $byId[$paymentTermsId]['days']);
        self::assertFalse($byId[$paymentTermsId]['is_default']);
        self::assertTrue($byId[$paymentTermsId]['active']);

        $client->request('PUT', "/api/v1/companies/{$companyId}/payment-terms/{$paymentTermsId}", server: self::HEADERS, content: json_encode([
            'name' => '60 dias',
            'days' => 60,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS);
        /** @var array{items: list<array{id: string, name: string, days: int}>} $updatedList */
        $updatedList = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $updatedById = array_column($updatedList['items'], null, 'id');
        self::assertSame('60 dias', $updatedById[$paymentTermsId]['name']);
        self::assertSame(60, $updatedById[$paymentTermsId]['days']);

        $client->request('DELETE', "/api/v1/companies/{$companyId}/payment-terms/{$paymentTermsId}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS);
        /** @var array{items: list<array{id: string, active: bool}>} $afterDeactivation */
        $afterDeactivation = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $afterDeactivationById = array_column($afterDeactivation['items'], null, 'id');
        self::assertFalse($afterDeactivationById[$paymentTermsId]['active'], 'Deactivation is active=false, not a hard delete.');
    }

    public function testMarkingADifferentPaymentTermsAsDefaultUnmarksThePreviousOne(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $originalDefaultId = $this->onlyPaymentTermsId($client, $companyId);

        $client->request('POST', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS, content: json_encode([
            'name' => '30 dias',
            'days' => 30,
            'is_default' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        /** @var array{id: string} $created */
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $newPaymentTermsId = $created['id'];

        $client->request('GET', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS);
        /** @var array{items: list<array{id: string, is_default: bool}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $defaults = array_values(array_filter($body['items'], static fn (array $t) => $t['is_default']));

        self::assertCount(1, $defaults, 'Exactly one payment terms must remain default.');
        self::assertSame($newPaymentTermsId, $defaults[0]['id']);

        $byId = array_column($body['items'], null, 'id');
        self::assertFalse($byId[$originalDefaultId]['is_default']);
    }

    public function testUpdatingAnUnknownPaymentTermsIsNotFound(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $client->request('PUT', "/api/v1/companies/{$companyId}/payment-terms/00000000-0000-7000-8000-000000000000", server: self::HEADERS, content: json_encode([
            'name' => 'X',
            'days' => 0,
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function onlyPaymentTermsId(KernelBrowser $client, string $companyId): string
    {
        $client->request('GET', "/api/v1/companies/{$companyId}/payment-terms", server: self::HEADERS);
        /** @var array{items: list<array{id: string}>} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertCount(1, $body['items']);

        return $body['items'][0]['id'];
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
        return \sprintf('payment-terms-%s@example.test', bin2hex(random_bytes(8)));
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
