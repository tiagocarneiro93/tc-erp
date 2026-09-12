<?php

declare(strict_types=1);

namespace App\Inventory\Application\Event;

use App\Inventory\Domain\Warehouse;
use App\Inventory\Domain\WarehouseId;
use App\Inventory\Domain\WarehouseRepository;
use App\Shared\Domain\Event\CompanyRegistered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * technical-scope.md §7.10.1/§6.10, docs/plans/phase-1.md task 1.9 decision
 * 4: every new company gets exactly one default warehouse, seeded here on
 * `CompanyRegistered` — same event-driven pattern as Company's own
 * default-profile seeding and Parties' "Consumidor final" customer
 * (docs/decisions/0004).
 *
 * Idempotent: `app:seed` backfills companies that predate this listener by
 * re-dispatching `CompanyRegistered` rather than depending on this module's
 * domain directly, which Deptrac forbids from `Platform\Infrastructure`.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class CreateDefaultWarehouseOnCompanyRegistered
{
    private const DEFAULT_CODE = 'PRINCIPAL';
    private const DEFAULT_NAME = 'Armazém principal';

    public function __construct(private readonly WarehouseRepository $warehouses)
    {
    }

    public function __invoke(CompanyRegistered $event): void
    {
        if (null !== $this->warehouses->findDefault($event->companyId)) {
            return;
        }

        $this->warehouses->save(Warehouse::create(
            WarehouseId::generate(),
            $event->companyId,
            self::DEFAULT_CODE,
            self::DEFAULT_NAME,
            null,
            true,
        ));
    }
}
