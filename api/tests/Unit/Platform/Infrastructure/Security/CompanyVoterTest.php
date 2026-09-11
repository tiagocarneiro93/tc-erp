<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Infrastructure\Security;

use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\RoleRepository;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Infrastructure\Security\CompanyVoter;
use App\Platform\Infrastructure\Security\SecurityUser;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class CompanyVoterTest extends TestCase
{
    public function testAbstainsForANonCompanyIdSubject(): void
    {
        $voter = $this->voter($this->createStub(MembershipRepository::class), $this->createStub(RoleRepository::class));

        $vote = $voter->vote($this->tokenFor(null), 'not-a-company-id', [CompanyVoter::MEMBER]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $vote);
    }

    public function testDeniesMembershipWhenThereIsNoMembership(): void
    {
        $memberships = $this->createStub(MembershipRepository::class);
        $memberships->method('find')->willReturn(null);
        $voter = $this->voter($memberships, $this->createStub(RoleRepository::class));
        $user = $this->userWithId(UserId::generate());

        $vote = $voter->vote($this->tokenFor($user), CompanyId::generate(), [CompanyVoter::MEMBER]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function testDeniesMembershipWhenItIsNotActive(): void
    {
        $userId = UserId::generate();
        $companyId = CompanyId::generate();
        $membership = Membership::create($userId, $companyId, 'owner', new \DateTimeImmutable());
        $suspended = new \ReflectionProperty(Membership::class, 'status');
        $suspended->setValue($membership, 'suspended');

        $memberships = $this->createStub(MembershipRepository::class);
        $memberships->method('find')->willReturn($membership);
        $voter = $this->voter($memberships, $this->createStub(RoleRepository::class));

        $vote = $voter->vote($this->tokenFor($this->userWithId($userId)), $companyId, [CompanyVoter::MEMBER]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $vote);
    }

    public function testGrantsMemberAttributeForAnActiveMembership(): void
    {
        $userId = UserId::generate();
        $companyId = CompanyId::generate();
        $membership = Membership::create($userId, $companyId, 'owner', new \DateTimeImmutable());

        $memberships = $this->createStub(MembershipRepository::class);
        $memberships->method('find')->willReturn($membership);
        $voter = $this->voter($memberships, $this->createStub(RoleRepository::class));

        $vote = $voter->vote($this->tokenFor($this->userWithId($userId)), $companyId, [CompanyVoter::MEMBER]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $vote);
    }

    public function testGrantsAPermissionOnlyWhenTheRoleHasIt(): void
    {
        $userId = UserId::generate();
        $companyId = CompanyId::generate();
        $membership = Membership::create($userId, $companyId, 'accountant', new \DateTimeImmutable());

        $memberships = $this->createStub(MembershipRepository::class);
        $memberships->method('find')->willReturn($membership);
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('permissionsFor')->willReturn(['documents.read']);
        $voter = $this->voter($memberships, $roles);
        $token = $this->tokenFor($this->userWithId($userId));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $companyId, ['documents.read']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, $companyId, ['documents.issue']));
    }

    private function voter(MembershipRepository $memberships, RoleRepository $roles): CompanyVoter
    {
        return new CompanyVoter($memberships, $roles);
    }

    private function userWithId(UserId $userId): SecurityUser
    {
        $user = User::register($userId, 'voter@example.test', 'Voter', 'hash', new \DateTimeImmutable());

        return new SecurityUser($user);
    }

    private function tokenFor(?SecurityUser $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
