<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
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
 * docs/plans/phase-1.md task 1.4: a default profile exists right after
 * `CreateCompany` (docs/decisions/0004's `CompanyRegistered` event), and can
 * then be read and updated by an owner (who has `company.manage`).
 */
final class CompanyProfileControllerTest extends WebTestCase
{
    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testGettingRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/companies/00000000-0000-7000-8000-000000000000/profile');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testANewCompanyAlreadyHasADefaultProfilePrefilledFromRegistration(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $nif = $this->uniqueNif();
        $companyId = $this->createCompany($client, $nif, 'Acme, Lda.');

        $client->request('GET', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS);
        self::assertResponseIsSuccessful();

        /** @var array{nif: string, legal_name: string, country: string, fiscal_region: string, vat_regime: string, cash_vat: bool, commercial_name: ?string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($nif, $body['nif']);
        self::assertSame('Acme, Lda.', $body['legal_name']);
        self::assertSame('PT', $body['country']);
        self::assertSame('PT', $body['fiscal_region']);
        self::assertSame('normal', $body['vat_regime']);
        self::assertFalse($body['cash_vat']);
        self::assertNull($body['commercial_name']);
    }

    public function testAnOwnerCanUpdateTheProfile(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $nif = $this->uniqueNif();
        $companyId = $this->createCompany($client, $nif);

        $client->request('PUT', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS, content: json_encode([
            'nif' => $nif,
            'legal_name' => 'Acme Updated, Lda.',
            'commercial_name' => 'Acme',
            'address' => 'Rua Principal, 1',
            'postal_code' => '1000-001',
            'city' => 'Lisboa',
            'country' => 'PT',
            'share_capital' => '5000.00',
            'registry_office' => 'Lisboa',
            'email' => 'geral@acme.example',
            'phone' => '+351210000000',
            'logo_key' => null,
            'fiscal_region' => 'PT-AC',
            'vat_regime' => 'normal',
            'cash_vat' => true,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS);
        /** @var array{legal_name: string, fiscal_region: string, share_capital: string, cash_vat: bool} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Acme Updated, Lda.', $body['legal_name']);
        self::assertSame('PT-AC', $body['fiscal_region']);
        self::assertSame('5000.00', $body['share_capital']);
        self::assertTrue($body['cash_vat']);
    }

    public function testUpdatingWithAnUnknownFiscalRegionIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $nif = $this->uniqueNif();
        $companyId = $this->createCompany($client, $nif);

        $client->request('PUT', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS, content: json_encode([
            'nif' => $nif,
            'legal_name' => 'Acme, Lda.',
            'country' => 'PT',
            'fiscal_region' => 'PT-XX',
            'vat_regime' => 'normal',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdatingWithAnUnknownCountryCodeIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $nif = $this->uniqueNif();
        $companyId = $this->createCompany($client, $nif);

        $client->request('PUT', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS, content: json_encode([
            'nif' => $nif,
            'legal_name' => 'Acme, Lda.',
            'country' => 'ZZ',
            'fiscal_region' => 'PT',
            'vat_regime' => 'normal',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testAMemberWithoutCompanyManagePermissionCannotUpdateTheProfile(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $nif = $this->uniqueNif();
        $companyId = $this->createCompany($client, $nif);

        $this->inviteAsReadOnly($client, $companyId);

        $client->request('PUT', "/api/v1/companies/{$companyId}/profile", server: self::HEADERS, content: json_encode([
            'nif' => $nif,
            'legal_name' => 'Acme, Lda.',
            'country' => 'PT',
            'fiscal_region' => 'PT',
            'vat_regime' => 'normal',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function inviteAsReadOnly(KernelBrowser $client, string $companyId): void
    {
        /** @var MembershipRepository $memberships */
        $memberships = static::getContainer()->get(MembershipRepository::class);
        $readOnlyUserId = $this->registerAndLogIn($client, $this->uniqueEmail(), 'read-only-password');
        $memberships->save(Membership::create($readOnlyUserId, CompanyId::fromString($companyId), 'read_only', new \DateTimeImmutable()));
    }

    private function createCompany(KernelBrowser $client, string $nif, string $legalName = 'A Company Lda'): string
    {
        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => $nif,
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
        return \sprintf('company-profile-%s@example.test', bin2hex(random_bytes(8)));
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
