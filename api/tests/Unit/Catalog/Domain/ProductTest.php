<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductId;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function types(): iterable
    {
        yield 'P is valid' => ['P', true];
        yield 'S is valid' => ['S', true];
        yield 'O is valid' => ['O', true];
        yield 'E is valid' => ['E', true];
        yield 'I is valid' => ['I', true];
        yield 'lowercase is invalid' => ['p', false];
        yield 'unknown letter is invalid' => ['X', false];
        yield 'empty is invalid' => ['', false];
    }

    #[DataProvider('types')]
    public function testIsValidType(string $type, bool $expected): void
    {
        self::assertSame($expected, Product::isValidType($type));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function kinds(): iterable
    {
        yield 'simple is valid' => ['simple', true];
        yield 'kit is valid (structurally allowed from this task on)' => ['kit', true];
        yield 'assembled is not yet valid' => ['assembled', false];
        yield 'empty is invalid' => ['', false];
    }

    #[DataProvider('kinds')]
    public function testIsValidKind(string $kind, bool $expected): void
    {
        self::assertSame($expected, Product::isValidKind($kind));
    }

    public function testCreateStartsActiveWithNullCosts(): void
    {
        $product = $this->product();

        self::assertTrue($product->active());
        self::assertNull($product->lastCost());
        self::assertNull($product->averageCost());
    }

    public function testUpdateChangesFieldsButNotKindOrActive(): void
    {
        $product = $this->product();

        $product->update('P002', 'New description', 'S', 'KG', '5901234123457', null, 'other-tax-rate-id', 'M01', true);

        self::assertSame('P002', $product->code());
        self::assertSame('New description', $product->description());
        self::assertSame('S', $product->type());
        self::assertSame('KG', $product->unitCode());
        self::assertSame('5901234123457', $product->barcode());
        self::assertSame('other-tax-rate-id', $product->taxRateId());
        self::assertSame('M01', $product->exemptionReasonCode());
        self::assertTrue($product->trackStock());
        self::assertSame('simple', $product->kind(), 'kind is fixed at creation, update() has no such parameter.');
        self::assertTrue($product->active());
    }

    public function testDeactivateSetsActiveFalse(): void
    {
        $product = $this->product();

        $product->deactivate();

        self::assertFalse($product->active());
    }

    private function product(): Product
    {
        return Product::create(
            ProductId::generate(),
            CompanyId::generate(),
            'P001',
            'Original description',
            'P',
            'simple',
            'UN',
            null,
            null,
            'a-tax-rate-id',
            null,
            false,
        );
    }
}
