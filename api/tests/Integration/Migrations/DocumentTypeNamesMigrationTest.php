<?php

declare(strict_types=1);

namespace App\Tests\Integration\Migrations;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Version20260912110000 backfills document_types.name for the twelve v1
 * types seeded by task 1.3.
 */
final class DocumentTypeNamesMigrationTest extends KernelTestCase
{
    public function testEveryDocumentTypeHasAName(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        $rows = $connection->fetchAllKeyValue('SELECT code, name FROM document_types');
        self::assertCount(12, $rows);

        foreach ($rows as $code => $name) {
            self::assertNotSame('', $name, \sprintf('%s should have a name.', $code));
        }

        self::assertSame('Fatura', $rows['FT']);
        self::assertSame('Recibo', $rows['RG']);
    }
}
