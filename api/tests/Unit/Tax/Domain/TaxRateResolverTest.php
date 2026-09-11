<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain;

use App\Shared\Domain\Decimal\Percentage;
use App\Tax\Domain\Exception\NoApplicableTaxRate;
use App\Tax\Domain\TaxRate;
use App\Tax\Domain\TaxRateId;
use App\Tax\Domain\TaxRateRepository;
use App\Tax\Domain\TaxRateResolver;
use PHPUnit\Framework\TestCase;

final class TaxRateResolverTest extends TestCase
{
    public function testResolvesTheRateTheRepositoryReturns(): void
    {
        $date = new \DateTimeImmutable('2026-01-01');
        $rate = new TaxRate(
            TaxRateId::generate(),
            'PT',
            'NOR',
            Percentage::fromString('23.00'),
            new \DateTimeImmutable('2011-01-01'),
            null,
            'Taxa normal',
        );

        $repository = $this->createMock(TaxRateRepository::class);
        $repository->method('findApplicable')->with('PT', 'NOR', $date)->willReturn($rate);

        $resolver = new TaxRateResolver($repository);

        self::assertSame($rate, $resolver->resolve('PT', 'NOR', $date));
    }

    public function testThrowsInsteadOfSilentlyPickingTheNearestRate(): void
    {
        $repository = $this->createMock(TaxRateRepository::class);
        $repository->method('findApplicable')->willReturn(null);

        $resolver = new TaxRateResolver($repository);

        $this->expectException(NoApplicableTaxRate::class);
        $resolver->resolve('PT-AC', 'RED', new \DateTimeImmutable('2000-01-01'));
    }
}
