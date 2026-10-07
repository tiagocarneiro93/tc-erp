<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain\Saft;

use App\Fiscal\Domain\Exception\InvalidSaftPeriod;
use App\Fiscal\Domain\Saft\SaftExportPeriod;
use PHPUnit\Framework\TestCase;

final class SaftExportPeriodTest extends TestCase
{
    public function testItIsInclusiveOfBothEndsAndExposesTheFiscalYear(): void
    {
        $period = new SaftExportPeriod(new \DateTimeImmutable('2026-03-01T15:00:00+00:00'), new \DateTimeImmutable('2026-03-31T09:00:00+00:00'));

        self::assertSame(2026, $period->fiscalYear());
        self::assertSame('2026-03-01T00:00:00+00:00', $period->from()->format('c'));
        self::assertSame('2026-04-01T00:00:00+00:00', $period->until()->format('c'), 'The upper bound is exclusive: midnight after the end date.');
    }

    public function testASingleDayIsAValidPeriod(): void
    {
        $period = new SaftExportPeriod(new \DateTimeImmutable('2026-03-05'), new \DateTimeImmutable('2026-03-05'));

        self::assertSame('2026-03-06', $period->until()->format('Y-m-d'));
    }

    public function testItRejectsAnEndBeforeTheStart(): void
    {
        $this->expectException(InvalidSaftPeriod::class);

        new SaftExportPeriod(new \DateTimeImmutable('2026-03-05'), new \DateTimeImmutable('2026-03-04'));
    }

    public function testItRejectsAPeriodCrossingNewYear(): void
    {
        $this->expectException(InvalidSaftPeriod::class);

        new SaftExportPeriod(new \DateTimeImmutable('2026-12-31'), new \DateTimeImmutable('2027-01-01'));
    }

    public function testItRejectsAYearTheSchemaDoesNotAllow(): void
    {
        $this->expectException(InvalidSaftPeriod::class);

        new SaftExportPeriod(new \DateTimeImmutable('1999-01-01'), new \DateTimeImmutable('1999-12-31'));
    }
}
