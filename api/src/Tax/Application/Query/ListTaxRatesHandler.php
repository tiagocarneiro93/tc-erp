<?php

declare(strict_types=1);

namespace App\Tax\Application\Query;

use App\Tax\Domain\TaxRate;
use App\Tax\Domain\TaxRateRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListTaxRatesHandler
{
    public function __construct(
        private readonly TaxRateRepository $rates,
    ) {
    }

    /**
     * @return list<TaxRate>
     */
    public function __invoke(ListTaxRates $query): array
    {
        return $this->rates->findAll($query->region, $query->asOf);
    }
}
