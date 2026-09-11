<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Application\Security\PermissionChecker;
use App\Platform\Domain\CompanyRepository;
use App\Platform\Domain\Exception\AlreadyAMember;
use App\Platform\Domain\Exception\PermissionDenied;
use App\Platform\Domain\Exception\UnknownRole;
use App\Platform\Domain\InvitationMailer;
use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\PasswordResetToken;
use App\Platform\Domain\PasswordResetTokenId;
use App\Platform\Domain\PasswordResetTokenRepository;
use App\Platform\Domain\RoleRepository;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Only a brand-new user gets an invitation email: they have no password to
 * log in with yet, so the same "set your password" link mechanism as
 * {@see RequestPasswordResetHandler} doubles as their invitation (CLAUDE.md
 * task 0.8: "no endpoint ever ... lets admins set a known password —
 * invites and resets by email link only"). Adding an *existing* user to
 * another company needs no email: they already have a working password.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class InviteUserToCompanyHandler
{
    private const TOKEN_TTL = 'P7D';

    public function __construct(
        private readonly UserRepository $users,
        private readonly CompanyRepository $companies,
        private readonly MembershipRepository $memberships,
        private readonly RoleRepository $roles,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly InvitationMailer $mailer,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
        private readonly Clock $clock,
        private readonly string $frontendUrl,
    ) {
    }

    public function __invoke(InviteUserToCompany $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('members.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if (null === $this->roles->find($command->role)) {
            throw new UnknownRole();
        }

        $now = $this->clock->now();
        $user = $this->users->findByEmail($command->email);
        $isNewUser = null === $user;

        if (null === $user) {
            $user = User::register(UserId::generate(), $command->email, $command->name, bin2hex(random_bytes(32)), $now);
            $this->users->save($user);
        }

        if (null !== $this->memberships->find($user->id(), $companyId)) {
            throw new AlreadyAMember();
        }

        $this->memberships->save(Membership::create($user->id(), $companyId, $command->role, $now));

        if ($isNewUser) {
            $this->sendInvitationEmail($user, $companyId, $now);
        }

        $this->auditLogger->log(
            'company.member_invited',
            'Membership',
            $user->id()->toString(),
            ['email' => $command->email, 'role' => $command->role],
            $command->invitedByUserId->toString(),
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    private function sendInvitationEmail(User $user, CompanyId $companyId, \DateTimeImmutable $now): void
    {
        $company = $this->companies->find($companyId);

        if (null === $company) {
            return;
        }

        $rawToken = bin2hex(random_bytes(32));

        $this->tokens->save(PasswordResetToken::issue(
            PasswordResetTokenId::generate(),
            $user->id(),
            hash('sha256', $rawToken),
            $now->add(new \DateInterval(self::TOKEN_TTL)),
            $now,
        ));

        $setPasswordUrl = \sprintf('%s/reset-password?token=%s', rtrim($this->frontendUrl, '/'), $rawToken);
        $this->mailer->sendInvitation($user->email(), $company->legalName(), $setPasswordUrl);
    }
}
