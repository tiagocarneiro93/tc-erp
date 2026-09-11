<?php

declare(strict_types=1);

namespace App\Shared\Application\Query;

use App\Shared\Domain\Country;
use App\Shared\Domain\CountryRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListCountriesHandler
{
    public function __construct(
        private readonly CountryRepository $countries,
    ) {
    }

    /**
     * @return list<Country>
     */
    public function __invoke(ListCountries $query): array
    {
        return $this->countries->findAll();
    }
}
