<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform;

use App\Platform\Domain\CompanyRepository;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\PasswordHasher;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\Nif;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The task 0.12 acceptance criterion: a fresh `make seed` gives a usable
 * demo — and running it again must not fail or duplicate anything.
 */
final class SeedDemoDataCommandTest extends KernelTestCase
{
    public function testSeedsTwoCompaniesAndTwoUsersAndIsIdempotent(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:seed');

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute([]));
        self::assertSame(0, $tester->execute([]), 'Running the seed command twice must not fail.');

        /** @var CompanyRepository $companies */
        $companies = self::getContainer()->get(CompanyRepository::class);
        $companyA = $companies->findByNif(Nif::fromString('500000018'));
        $companyB = $companies->findByNif(Nif::fromString('500000026'));
        self::assertNotNull($companyA);
        self::assertNotNull($companyB);

        /** @var UserRepository $users */
        $users = self::getContainer()->get(UserRepository::class);
        $owner = $users->findByEmail('owner@demo.tc-erp.test');
        $accountant = $users->findByEmail('accountant@demo.tc-erp.test');
        self::assertNotNull($owner);
        self::assertNotNull($accountant);
        self::assertFalse($owner->mustChangePassword());

        /** @var PasswordHasher $hasher */
        $hasher = self::getContainer()->get(PasswordHasher::class);
        self::assertTrue($hasher->verify($owner->passwordHash(), 'demo-password'));

        /** @var MembershipRepository $memberships */
        $memberships = self::getContainer()->get(MembershipRepository::class);
        self::assertSame('owner', $memberships->find($owner->id(), $companyA->id())?->role());
        self::assertSame('owner', $memberships->find($owner->id(), $companyB->id())?->role());
        self::assertSame('accountant', $memberships->find($accountant->id(), $companyA->id())?->role());
        self::assertSame('accountant', $memberships->find($accountant->id(), $companyB->id())?->role());
        self::assertCount(2, $memberships->findByUser($owner->id()));
        self::assertCount(2, $memberships->findByUser($accountant->id()));
    }
}
