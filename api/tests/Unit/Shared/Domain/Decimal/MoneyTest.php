<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Decimal;

use App\Shared\Domain\Decimal\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testRoundTripAtTwoDecimals(): void
    {
        self::assertSame('29.97', Money::fromString('29.97')->toString());
        self::assertSame('0.00', Money::zero()->toString());
    }

    public function testRejectsMoreThanTwoDecimalPlaces(): void
    {
        $this->expectException(\Brick\Math\Exception\RoundingNecessaryException::class);

        Money::fromString('1.005');
    }

    public function testArithmeticAndComparison(): void
    {
        $sum = Money::fromString('24.37')->plus(Money::fromString('5.60'));
        self::assertSame('29.97', $sum->toString());

        self::assertTrue(Money::fromString('1.00')->isPositive());
        self::assertTrue(Money::fromString('-1.00')->isNegative());
        self::assertTrue(Money::zero()->isZero());
        self::assertTrue(Money::fromString('1.00')->equals(Money::fromString('1.00')));
        self::assertSame(-1, Money::fromString('1.00')->compareTo(Money::fromString('2.00')));
    }
}
