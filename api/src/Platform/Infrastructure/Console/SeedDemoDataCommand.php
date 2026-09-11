<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\Console;

use App\Platform\Domain\Company;
use App\Platform\Domain\CompanyRepository;
use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `make seed` (docs/PLAN.md task 0.12): two demo companies and two demo
 * users usable straight away — no forced password change, since these
 * accounts exist purely to make the app immediately explorable.
 *
 * Bypasses the `CreateCompany`/`InviteUserToCompany` use cases and their
 * permission and audit-log side effects (unlike issued fiscal documents,
 * global platform data — users, companies, memberships — carries no
 * CLAUDE.md immutability or "issue through the use case" rule) and simply
 * builds the entities directly, the same way integration tests already do.
 * Idempotent: safe to run again against a database that already has this
 * demo data.
 */
#[AsCommand(name: 'app:seed', description: 'Load development fixtures: two demo companies, an owner and an accountant.')]
final class SeedDemoDataCommand extends Command
{
    private const PASSWORD = 'demo-password';

    public function __construct(
        private readonly UserRepository $users,
        private readonly CompanyRepository $companies,
        private readonly MembershipRepository $memberships,
        private readonly PasswordHasher $passwordHasher,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = $this->clock->now();

        $companyA = $this->ensureCompany($io, Nif::fromString('500000018'), 'Padaria Exemplo, Lda.', $now);
        $companyB = $this->ensureCompany($io, Nif::fromString('500000026'), 'Exemplo Consultoria, Lda.', $now);

        $owner = $this->ensureUser($io, 'owner@demo.tc-erp.test', 'Demo Owner', $now);
        $accountant = $this->ensureUser($io, 'accountant@demo.tc-erp.test', 'Demo Accountant', $now);

        $this->ensureMembership($io, $owner, $companyA, 'owner', $now);
        $this->ensureMembership($io, $owner, $companyB, 'owner', $now);
        $this->ensureMembership($io, $accountant, $companyA, 'accountant', $now);
        $this->ensureMembership($io, $accountant, $companyB, 'accountant', $now);

        $io->success([
            'Demo data ready.',
            \sprintf('owner@demo.tc-erp.test / %s — owner of both companies', self::PASSWORD),
            \sprintf('accountant@demo.tc-erp.test / %s — accountant on both companies', self::PASSWORD),
        ]);

        return Command::SUCCESS;
    }

    private function ensureCompany(SymfonyStyle $io, Nif $nif, string $legalName, \DateTimeImmutable $now): CompanyId
    {
        $existing = $this->companies->findByNif($nif);

        if (null !== $existing) {
            $io->writeln(\sprintf('Company "%s" already exists, skipping.', $legalName));

            return $existing->id();
        }

        $companyId = CompanyId::generate();
        $this->companies->save(Company::register($companyId, $nif, $legalName, $now));
        $io->writeln(\sprintf('Created company "%s".', $legalName));

        return $companyId;
    }

    private function ensureUser(SymfonyStyle $io, string $email, string $name, \DateTimeImmutable $now): UserId
    {
        $existing = $this->users->findByEmail($email);

        if (null !== $existing) {
            $io->writeln(\sprintf('User "%s" already exists, skipping.', $email));

            return $existing->id();
        }

        $userId = UserId::generate();
        $user = User::register($userId, $email, $name, $this->passwordHasher->hash(self::PASSWORD), $now);
        // A demo account should work immediately, not force a password
        // change on first login (CLAUDE.md task 0.8's rule is for real,
        // admin-provisioned accounts with an unknown-to-them temporary
        // password — not the case here, since the password is public).
        $user->changePassword($this->passwordHasher->hash(self::PASSWORD), $now);
        $this->users->save($user);
        $io->writeln(\sprintf('Created user "%s".', $email));

        return $userId;
    }

    private function ensureMembership(SymfonyStyle $io, UserId $userId, CompanyId $companyId, string $role, \DateTimeImmutable $now): void
    {
        if (null !== $this->memberships->find($userId, $companyId)) {
            return;
        }

        $this->memberships->save(Membership::create($userId, $companyId, $role, $now));
        $io->writeln(\sprintf('Granted role "%s".', $role));
    }
}
