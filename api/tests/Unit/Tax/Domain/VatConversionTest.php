<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Percentage;
use App\Tax\Domain\VatConversion;
use PHPUnit\Framework\TestCase;

/**
 * technical-scope.md §7.9.8's own worked example: "9,99 / 1,23 = 8,121951".
 */
final class VatConversionTest extends TestCase
{
    public function testToNetMatchesTheScopesWorkedExample(): void
    {
        $net = VatConversion::toNet(Decimal::fromString('9.99'), Percentage::fromString('23.00'));

        self::assertSame('8.121951', $net->toString());
    }

    public function testToGrossIsTheInverseOperation(): void
    {
        $gross = VatConversion::toGross(Decimal::fromString('8.121951'), Percentage::fromString('23.00'));

        // Not an exact round-trip (the net value itself is already rounded
        // to 6 decimals), but recovers the original gross to the same scale.
        self::assertSame('9.990000', $gross->toString());
    }

    public function testToNetWithAZeroRateReturnsTheSameAmount(): void
    {
        $net = VatConversion::toNet(Decimal::fromString('100.00'), Percentage::fromString('0.00'));

        self::assertSame('100.000000', $net->toString());
    }
}
