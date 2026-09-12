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
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Event\CompanyRegistered;
use App\Shared\Domain\Nif;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

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
 *
 * `ensureCompanyDefaults()` re-dispatches `CompanyRegistered` for EVERY
 * demo company on every run, not only newly-created ones, so each module's
 * own listener backfills whatever it's missing (company profile,
 * "Consumidor final" customer, default warehouse, default payment terms —
 * docs/decisions/0004) — those listeners are themselves idempotent, so this
 * is safe to repeat. This command deliberately does *not* depend on
 * Company/Parties/Inventory's domain directly to build those rows itself:
 * Deptrac forbids `Platform\Infrastructure` from depending on another
 * module's Domain or Application layer, so dispatching the same
 * cross-module event `CreateCompanyHandler` uses is the only way to
 * backfill without a Deptrac exception. Their absence was a real 500 in
 * the web app (`CompanyProfileNotFound` is a should-never-happen invariant,
 * not a 404), surfaced when testing task 1.10's screens against a demo
 * company.
 */
#[AsCommand(name: 'app:seed', description: 'Load development fixtures: two demo companies, an owner and an accountant.')]
final class SeedDemoDataCommand extends Command
{
    private const PASSWORD = 'demo-password';

    public function __construct(
        private readonly UserRepository $users,
        private readonly CompanyRepository $companies,
        private readonly MembershipRepository $memberships,
        private readonly CompanyContext $companyContext,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private readonly Connection $connection,
        private readonly MessageBusInterface $eventBus,
        private readonly PasswordHasher $passwordHasher,
        private readonly Clock $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = $this->clock->now();

        $nifA = Nif::fromString('500000018');
        $nifB = Nif::fromString('500000026');
        $companyA = $this->ensureCompany($io, $nifA, 'Padaria Exemplo, Lda.', $now);
        $companyB = $this->ensureCompany($io, $nifB, 'Exemplo Consultoria, Lda.', $now);

        $this->ensureCompanyDefaults($companyA, $nifA, 'Padaria Exemplo, Lda.', $now);
        $this->ensureCompanyDefaults($companyB, $nifB, 'Exemplo Consultoria, Lda.', $now);

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

    /**
     * Re-dispatches `CompanyRegistered` so each module's own listener
     * backfills whatever company-scoped default row it's missing — those
     * listeners check before inserting, so this is safe for a company that
     * already has some or all of them.
     */
    private function ensureCompanyDefaults(CompanyId $companyId, Nif $nif, string $legalName, \DateTimeImmutable $now): void
    {
        $this->companyContext->set($companyId);
        $this->connection->beginTransaction();

        try {
            $this->eventBus->dispatch(new CompanyRegistered($companyId, $nif, $legalName, $now));
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        } finally {
            $this->companyContext->clear();
        }
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
