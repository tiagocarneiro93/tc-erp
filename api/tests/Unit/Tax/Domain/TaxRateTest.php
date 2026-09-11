<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain;

use App\Shared\Domain\Decimal\Percentage;
use App\Tax\Domain\TaxRate;
use App\Tax\Domain\TaxRateId;
use PHPUnit\Framework\TestCase;

final class TaxRateTest extends TestCase
{
    public function testIsValidAtIncludesTheValidFromBoundary(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2026-01-01'), null);

        self::assertFalse($rate->isValidAt(new \DateTimeImmutable('2025-12-31')));
        self::assertTrue($rate->isValidAt(new \DateTimeImmutable('2026-01-01')));
    }

    public function testIsValidAtIncludesTheValidToBoundaryWhenSet(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'));

        self::assertTrue($rate->isValidAt(new \DateTimeImmutable('2026-12-31')));
        self::assertFalse($rate->isValidAt(new \DateTimeImmutable('2027-01-01')));
    }

    public function testIsValidAtWithNoEndDateIsOpenEnded(): void
    {
        $rate = $this->rate(new \DateTimeImmutable('2026-01-01'), null);

        self::assertTrue($rate->isValidAt(new \DateTimeImmutable('2099-01-01')));
    }

    private function rate(\DateTimeImmutable $validFrom, ?\DateTimeImmutable $validTo): TaxRate
    {
        return new TaxRate(TaxRateId::generate(), 'PT', 'NOR', Percentage::fromString('23.00'), $validFrom, $validTo, 'Taxa normal');
    }
}
