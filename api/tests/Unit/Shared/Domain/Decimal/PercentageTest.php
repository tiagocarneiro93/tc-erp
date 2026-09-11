<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Decimal;

use App\Shared\Domain\Decimal\Percentage;
use PHPUnit\Framework\TestCase;

final class PercentageTest extends TestCase
{
    public function testAsMultiplier(): void
    {
        self::assertSame('0.23', Percentage::fromString('23')->asMultiplier()->__toString());
        self::assertSame('0.065', Percentage::fromString('6.5')->asMultiplier()->__toString());
        self::assertSame('0.00', Percentage::fromString('0')->asMultiplier()->__toString());
    }
}
