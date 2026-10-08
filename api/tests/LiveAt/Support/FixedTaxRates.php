<?php

declare(strict_types=1);

namespace App\Tests\LiveAt\Support;

use App\Shared\Domain\Decimal\Percentage;
use App\Tax\Domain\TaxRate;
use App\Tax\Domain\TaxRateId;
use App\Tax\Domain\TaxRateRepository;

/**
 * Mainland rates for the live suite, which has no database to read
 * `tax_rates` from. The same figures `Version20260911211300` seeds; only the
 * continental region is supported here (the regional rates are not needed to
 * prove the AT communication, and a wrong copy of them would only mislead).
 * `ISE` (exempt) is 0 % and needs an exemption code on the line.
 */
final class FixedTaxRates implements TaxRateRepository
{
    private const PERCENTAGES = ['NOR' => '23.00', 'INT' => '13.00', 'RED' => '6.00', 'ISE' => '0.00'];

    public function findAll(?string $region, ?\DateTimeImmutable $asOf): array
    {
        $rates = [];

        foreach (self::PERCENTAGES as $code => $percentage) {
            $rates[] = new TaxRate(TaxRateId::generate(), 'PT', $code, Percentage::fromString($percentage), new \DateTimeImmutable('2011-01-01'), null, 'live suite');
        }

        return $rates;
    }

    public function findApplicable(string $region, string $code, \DateTimeImmutable $date): ?TaxRate
    {
        if ('PT' !== $region || !isset(self::PERCENTAGES[$code])) {
            return null;
        }

        return new TaxRate(TaxRateId::generate(), $region, $code, Percentage::fromString(self::PERCENTAGES[$code]), new \DateTimeImmutable('2011-01-01'), null, 'live suite');
    }
}
