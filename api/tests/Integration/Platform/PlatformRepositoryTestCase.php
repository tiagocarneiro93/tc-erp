<?php

declare(strict_types=1);

namespace App\Tests\Integration\Platform;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Wraps every test in a transaction rolled back in tearDown, so integration
 * tests can freely insert real rows against real PostgreSQL (CLAUDE.md —
 * integration tests run against real PostgreSQL, not SQLite) without
 * polluting the test database between runs.
 */
abstract class PlatformRepositoryTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->entityManager = $entityManager;
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        $this->entityManager->clear();

        parent::tearDown();
    }
}
