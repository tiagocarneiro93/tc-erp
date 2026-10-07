<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Sealing;

use App\Output\Domain\SealingFailed;
use App\Output\Infrastructure\Sealing\FakeElectronicSealer;
use App\Output\Infrastructure\Sealing\UnconfiguredElectronicSealer;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

final class ElectronicSealersTest extends TestCase
{
    public function testTheFakeKeepsTheOriginalBytesIntactAndMarksTheFileAsNotReallySealed(): void
    {
        $pdf = "%PDF-1.4\nbody\n%%EOF";
        $company = CompanyId::generate();

        $sealed = (new FakeElectronicSealer())->seal($company, $pdf);

        self::assertStringStartsWith($pdf, $sealed);
        self::assertStringContainsString(FakeElectronicSealer::MARKER, $sealed);
        self::assertStringContainsString('%sha256='.hash('sha256', $pdf), $sealed);
        self::assertStringContainsString('%company='.$company->toString(), $sealed);
    }

    public function testTheUnconfiguredSealerRefusesInsteadOfLettingAnUnsealedPdfThrough(): void
    {
        $this->expectException(SealingFailed::class);
        $this->expectExceptionMessage('No electronic seal provider');

        (new UnconfiguredElectronicSealer())->seal(CompanyId::generate(), '%PDF');
    }
}
