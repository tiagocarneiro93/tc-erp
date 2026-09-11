<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform;

use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The task 0.10 acceptance criterion: creation, listing, invitations and
 * permission checks, end to end.
 *
 * `KernelBrowser` reboots the kernel (a fresh container, a fresh
 * EntityManager) before every `request()`, so repositories are always
 * fetched fresh from `static::getContainer()` right where they are used —
 * holding on to one obtained before a request would read back a stale,
 * pre-request identity map instead of what the request actually persisted.
 */
final class CompanyOnboardingFlowTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private const HEADERS = ['HTTP_X-Requested-With' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];

    public function testCreatingListingInvitingChangingRoleAndRemovingAMember(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');

        // 1. Create a company — the caller becomes its owner.
        $companyId = $this->createCompany($client, 'Acme Lda');

        // 2. It shows up in "my companies", with the owner role.
        $client->request('GET', '/api/v1/companies', server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{id: string, role: string, legal_name: string}>} $list */
        $list = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $mine = array_values(array_filter($list['items'], static fn (array $c) => $c['id'] === $companyId));
        self::assertCount(1, $mine);
        self::assertSame('owner', $mine[0]['role']);
        self::assertSame('Acme Lda', $mine[0]['legal_name']);

        // 3. Invite a brand-new user — they get an email with a set-password link.
        $memberEmail = $this->uniqueEmail();
        $client->request('POST', "/api/v1/companies/{$companyId}/users", server: self::HEADERS, content: json_encode([
            'email' => $memberEmail,
            'name' => 'New Member',
            'role' => 'accountant',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $sentEmail = self::getMailerMessage();
        self::assertNotNull($sentEmail);
        self::assertEmailTextBodyContains($sentEmail, 'reset-password?token=');
        self::assertEmailTextBodyContains($sentEmail, 'Acme Lda');

        $memberId = $this->findUserId($memberEmail);
        $membership = $this->findMembership($memberId, $companyId);
        self::assertNotNull($membership);
        self::assertSame('accountant', $membership->role());

        // 3b. The company's member list shows both the owner and the new member.
        $client->request('GET', "/api/v1/companies/{$companyId}/users", server: self::HEADERS);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{user_id: string, email: string, role: string}>} $membersList */
        $membersList = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $roleByEmail = array_column($membersList['items'], 'role', 'email');
        self::assertSame('accountant', $roleByEmail[$memberEmail]);
        self::assertCount(2, $membersList['items']);

        // 4. Change their role.
        $client->request('PUT', "/api/v1/companies/{$companyId}/users/{$memberId->toString()}", server: self::HEADERS, content: json_encode([
            'role' => 'stock',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $membership = $this->findMembership($memberId, $companyId);
        self::assertNotNull($membership);
        self::assertSame('stock', $membership->role());

        // 5. Remove them.
        $client->request('DELETE', "/api/v1/companies/{$companyId}/users/{$memberId->toString()}", server: self::HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->findMembership($memberId, $companyId));
    }

    public function testInvitingAnAlreadyRegisteredUserAddsMembershipWithoutAnEmail(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        $existingEmail = $this->uniqueEmail();
        $existingUserId = $this->registerUser($existingEmail, 'their-password');

        $client->request('POST', "/api/v1/companies/{$companyId}/users", server: self::HEADERS, content: json_encode([
            'email' => $existingEmail,
            'name' => 'Ignored',
            'role' => 'read_only',
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertNull(self::getMailerMessage(), 'An existing user already has a password; inviting them must not email a reset link.');
        self::assertNotNull($this->findMembership($existingUserId, $companyId));
    }

    public function testInvitingTheSamePersonTwiceIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);
        $memberEmail = $this->uniqueEmail();

        $invite = static fn () => $client->request('POST', "/api/v1/companies/{$companyId}/users", server: self::HEADERS, content: json_encode([
            'email' => $memberEmail,
            'name' => 'Member',
            'role' => 'read_only',
        ], \JSON_THROW_ON_ERROR));

        $invite();
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $invite();
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testCreatingACompanyWithADuplicateNifIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $nif = $this->uniqueNif();

        $create = static fn () => $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            'nif' => $nif,
            'legal_name' => 'Whatever Lda',
        ], \JSON_THROW_ON_ERROR));

        $create();
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $create();
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testCreatingACompanyWithAnInvalidNifIsRejected(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');

        $client->request('POST', '/api/v1/companies', server: self::HEADERS, content: json_encode([
            // Wrong length: never valid, regardless of check digit.
            'nif' => '12345678',
            'legal_name' => 'Whatever Lda',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testANonMemberGetsNotFoundNotForbidden(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        // A second, unrelated user, on the same client (logging in again
        // simply replaces the session's authenticated user).
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'other-password');

        $client->request('POST', "/api/v1/companies/{$companyId}/users", server: self::HEADERS, content: json_encode([
            'email' => $this->uniqueEmail(),
            'name' => 'X',
            'role' => 'read_only',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAMemberWithoutMembersManagePermissionCannotInvite(): void
    {
        $client = static::createClient();
        $this->registerAndLogIn($client, $this->uniqueEmail(), 'owner-password');
        $companyId = $this->createCompany($client);

        // A read_only member of the same company (no members.manage permission).
        $readOnlyUserId = $this->registerAndLogIn($client, $this->uniqueEmail(), 'read-only-password');

        /** @var MembershipRepository $memberships */
        $memberships = static::getContainer()->get(MembershipRepository::class);
        $memberships->save(Membership::create($readOnlyUserId, CompanyId::fromString($companyId), 'read_only', new \DateTimeImmutable()));

        $client->request('POST', "/api/v1/companies/{$companyId}/users", server: self::HEADERS, content: json_encode([
            'email' => $this->uniqueEmail(),
            'name' => 'X',
            'role' => 'read_only',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
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
        $userId = $this->registerUser($email, $password);

        $client->request('POST', '/api/v1/auth/login', server: self::HEADERS, content: json_encode([
            'email' => $email,
            'password' => $password,
        ], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return $userId;
    }

    private function registerUser(string $email, string $password): UserId
    {
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        /** @var PasswordHasher $hasher */
        $hasher = static::getContainer()->get(PasswordHasher::class);

        $user = User::register(UserId::generate(), $email, 'Flow User', $hasher->hash($password), new \DateTimeImmutable());
        $user->changePassword($hasher->hash($password), new \DateTimeImmutable());
        $users->save($user);

        return $user->id();
    }

    private function findUserId(string $email): UserId
    {
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findByEmail($email);
        self::assertNotNull($user);

        return $user->id();
    }

    private function findMembership(UserId $userId, string $companyId): ?Membership
    {
        /** @var MembershipRepository $memberships */
        $memberships = static::getContainer()->get(MembershipRepository::class);

        return $memberships->find($userId, CompanyId::fromString($companyId));
    }

    private function uniqueEmail(): string
    {
        return \sprintf('%s@example.test', bin2hex(random_bytes(8)));
    }

    private function uniqueNif(): string
    {
        // Same modulus-11 construction Nif::isValid checks, seeded with a
        // random 8-digit prefix so concurrent test runs never collide.
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
