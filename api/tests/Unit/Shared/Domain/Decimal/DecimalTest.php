<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Decimal;

use App\Shared\Domain\Decimal\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function testRoundTripPreservesScale(): void
    {
        self::assertSame('10.50', Decimal::fromString('10.50')->toString());
    }

    public function testArithmetic(): void
    {
        $sum = Decimal::fromString('1.5')->plus(Decimal::fromString('2.25'));
        self::assertSame('3.75', $sum->toString());

        $diff = Decimal::fromString('5')->minus(Decimal::fromString('1.5'));
        self::assertSame('3.5', $diff->toString());
    }

    public function testComparisonAndSign(): void
    {
        self::assertTrue(Decimal::fromString('0')->isZero());
        self::assertTrue(Decimal::fromString('-1')->isNegative());
        self::assertTrue(Decimal::fromString('1')->isPositive());
        self::assertTrue(Decimal::fromString('1')->equals(Decimal::fromString('1.0')));
        self::assertSame(0, Decimal::fromString('1')->compareTo(Decimal::fromString('1.0')));
        self::assertSame(-1, Decimal::fromString('1')->compareTo(Decimal::fromString('2')));
    }
}
