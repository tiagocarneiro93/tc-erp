<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Decimal;

use App\Shared\Domain\Decimal\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function testNormalisesToScaleSix(): void
    {
        self::assertSame('1.500000', Quantity::fromString('1.5')->toString());
        self::assertSame('3.000000', Quantity::fromString('3')->toString());
    }

    public function testRejectsMoreThanSixDecimalPlaces(): void
    {
        $this->expectException(\Brick\Math\Exception\RoundingNecessaryException::class);

        Quantity::fromString('1.1234567');
    }
}
