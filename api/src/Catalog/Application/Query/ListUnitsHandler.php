<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

use App\Catalog\Domain\Unit;
use App\Catalog\Domain\UnitRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListUnitsHandler
{
    public function __construct(
        private readonly UnitRepository $units,
    ) {
    }

    /**
     * @return list<Unit>
     */
    public function __invoke(ListUnits $query): array
    {
        return $this->units->findAll();
    }
}
