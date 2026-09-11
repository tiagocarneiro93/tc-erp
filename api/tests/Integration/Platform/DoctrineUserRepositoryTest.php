<?php

declare(strict_types=1);

namespace App\Tests\Integration\Platform;

use App\Platform\Domain\User;
use App\Platform\Domain\UserId;
use App\Platform\Domain\UserRepository;

final class DoctrineUserRepositoryTest extends PlatformRepositoryTestCase
{
    public function testSaveAndFindRoundTrip(): void
    {
        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepository::class);

        $id = UserId::generate();
        $user = User::register($id, 'jane@example.test', 'Jane Doe', 'hashed-password', new \DateTimeImmutable());
        $repository->save($user);
        $this->entityManager->clear();

        $found = $repository->find($id);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->id()));
        self::assertSame('jane@example.test', $found->email());
        self::assertTrue($found->mustChangePassword());
    }

    public function testFindByEmail(): void
    {
        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepository::class);

        $user = User::register(UserId::generate(), 'find-me@example.test', 'Find Me', 'hash', new \DateTimeImmutable());
        $repository->save($user);
        $this->entityManager->clear();

        self::assertNotNull($repository->findByEmail('find-me@example.test'));
        self::assertNull($repository->findByEmail('nobody@example.test'));
    }

    public function testChangePasswordPersists(): void
    {
        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepository::class);

        $id = UserId::generate();
        $user = User::register($id, 'pwd@example.test', 'Pwd', 'old-hash', new \DateTimeImmutable());
        $repository->save($user);
        $this->entityManager->clear();

        $reloaded = $repository->find($id);
        self::assertNotNull($reloaded);
        $reloaded->changePassword('new-hash', new \DateTimeImmutable());
        $repository->save($reloaded);
        $this->entityManager->clear();

        $final = $repository->find($id);
        self::assertNotNull($final);
        self::assertSame('new-hash', $final->passwordHash());
        self::assertFalse($final->mustChangePassword());
        self::assertNotNull($final->passwordChangedAt());
    }
}
