<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\ProductComponent;
use App\Catalog\Domain\ProductId;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Decimal\Quantity;
use PHPUnit\Framework\TestCase;

final class ProductComponentTest extends TestCase
{
    public function testSetCapturesTheGivenQuantityAndSortOrder(): void
    {
        $companyId = CompanyId::generate();
        $kitId = ProductId::generate();
        $componentId = ProductId::generate();

        $component = ProductComponent::set($companyId, $kitId, $componentId, Quantity::fromString('2.5'), 1);

        self::assertSame($companyId, $component->companyId());
        self::assertSame($kitId, $component->kitProductId());
        self::assertSame($componentId, $component->componentProductId());
        self::assertSame('2.500000', $component->quantity()->toString());
        self::assertSame(1, $component->sortOrder());
    }

    public function testUpdateReplacesQuantityAndSortOrderWithoutChangingIdentity(): void
    {
        $companyId = CompanyId::generate();
        $kitId = ProductId::generate();
        $componentId = ProductId::generate();
        $component = ProductComponent::set($companyId, $kitId, $componentId, Quantity::fromString('1'), 0);

        $component->update(Quantity::fromString('3'), 5);

        self::assertSame($kitId, $component->kitProductId());
        self::assertSame($componentId, $component->componentProductId());
        self::assertSame('3.000000', $component->quantity()->toString());
        self::assertSame(5, $component->sortOrder());
    }
}
