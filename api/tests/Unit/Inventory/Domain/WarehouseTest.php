<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\Warehouse;
use App\Inventory\Domain\WarehouseId;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

final class WarehouseTest extends TestCase
{
    public function testCreateStartsActiveWithTheGivenFields(): void
    {
        $warehouse = Warehouse::create(WarehouseId::generate(), CompanyId::generate(), 'ARM1', 'Armazém 1', 'Rua Principal, 1', true);

        self::assertSame('ARM1', $warehouse->code());
        self::assertSame('Armazém 1', $warehouse->name());
        self::assertSame('Rua Principal, 1', $warehouse->address());
        self::assertTrue($warehouse->isDefault());
        self::assertTrue($warehouse->active());
    }

    public function testUpdateReplacesFieldsWithoutChangingIdentity(): void
    {
        $id = WarehouseId::generate();
        $companyId = CompanyId::generate();
        $warehouse = Warehouse::create($id, $companyId, 'ARM1', 'Armazém 1', null, false);

        $warehouse->update('ARM2', 'Armazém 2', 'Rua Nova, 2', true);

        self::assertSame($id, $warehouse->id());
        self::assertSame($companyId, $warehouse->companyId());
        self::assertSame('ARM2', $warehouse->code());
        self::assertSame('Armazém 2', $warehouse->name());
        self::assertSame('Rua Nova, 2', $warehouse->address());
        self::assertTrue($warehouse->isDefault());
    }

    public function testUnmarkAsDefaultClearsTheFlag(): void
    {
        $warehouse = Warehouse::create(WarehouseId::generate(), CompanyId::generate(), 'ARM1', 'Armazém 1', null, true);

        $warehouse->unmarkAsDefault();

        self::assertFalse($warehouse->isDefault());
    }

    public function testDeactivateClearsActive(): void
    {
        $warehouse = Warehouse::create(WarehouseId::generate(), CompanyId::generate(), 'ARM1', 'Armazém 1', null, true);

        $warehouse->deactivate();

        self::assertFalse($warehouse->active());
    }
}
