<?php

declare(strict_types=1);

namespace App\Tests\Integration\Platform;

use App\Platform\Domain\Company;
use App\Platform\Domain\CompanyRepository;
use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;

final class DoctrineMembershipRepositoryTest extends PlatformRepositoryTestCase
{
    public function testSaveAndFindRoundTrip(): void
    {
        [$userId, $companyId] = $this->createUserAndCompany();

        /** @var MembershipRepository $repository */
        $repository = self::getContainer()->get(MembershipRepository::class);

        $membership = Membership::create($userId, $companyId, 'owner', new \DateTimeImmutable());
        $repository->save($membership);
        $this->entityManager->clear();

        $found = $repository->find($userId, $companyId);

        self::assertNotNull($found);
        self::assertSame('owner', $found->role());
        self::assertSame('active', $found->status());
    }

    public function testFindByUserReturnsAllMemberships(): void
    {
        [$userId, $companyId] = $this->createUserAndCompany();
        $secondCompanyId = CompanyId::generate();
        /** @var CompanyRepository $companyRepository */
        $companyRepository = self::getContainer()->get(CompanyRepository::class);
        $companyRepository->save(Company::register($secondCompanyId, Nif::fromString('123456789'), 'Second Co', new \DateTimeImmutable()));

        /** @var MembershipRepository $repository */
        $repository = self::getContainer()->get(MembershipRepository::class);
        $repository->save(Membership::create($userId, $companyId, 'owner', new \DateTimeImmutable()));
        $repository->save(Membership::create($userId, $secondCompanyId, 'accountant', new \DateTimeImmutable()));
        $this->entityManager->clear();

        $memberships = $repository->findByUser($userId);

        self::assertCount(2, $memberships);
    }

    /**
     * @return array{0: UserId, 1: CompanyId}
     */
    private function createUserAndCompany(): array
    {
        /** @var UserRepository $userRepository */
        $userRepository = self::getContainer()->get(UserRepository::class);
        /** @var CompanyRepository $companyRepository */
        $companyRepository = self::getContainer()->get(CompanyRepository::class);

        $userId = UserId::generate();
        $userRepository->save(User::register($userId, 'member@example.test', 'Member', 'hash', new \DateTimeImmutable()));

        $companyId = CompanyId::generate();
        $companyRepository->save(Company::register($companyId, Nif::fromString('999999990'), 'First Co', new \DateTimeImmutable()));

        return [$userId, $companyId];
    }
}
