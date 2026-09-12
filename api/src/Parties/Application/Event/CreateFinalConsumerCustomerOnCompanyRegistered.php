<?php

declare(strict_types=1);

namespace App\Parties\Application\Event;

use App\Parties\Domain\Customer;
use App\Parties\Domain\CustomerId;
use App\Parties\Domain\CustomerRepository;
use App\Shared\Domain\Event\CompanyRegistered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * technical-scope.md §6.3: seeds the generic "Consumidor final" customer
 * (NIF 999999990 — a legitimately check-digit-valid NIF, not a special
 * case for {@see \App\Shared\Domain\Nif}) so it exists as soon as the
 * company does. **[VERIFY]** noted in the scope: rules/limits for
 * invoices issued without a customer NIF are not enforced yet — that's a
 * Phase 2 issuance concern — this only seeds the record
 * (docs/plans/phase-1.md task 1.5). Same event-driven pattern as
 * Company's own default-profile seeding (docs/decisions/0004).
 *
 * Idempotent: `app:seed` backfills companies that predate this listener by
 * re-dispatching `CompanyRegistered` rather than depending on this module's
 * domain directly, which Deptrac forbids from `Platform\Infrastructure`.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class CreateFinalConsumerCustomerOnCompanyRegistered
{
    private const FINAL_CONSUMER_NIF = '999999990';
    private const FINAL_CONSUMER_CODE = 'CF';
    private const FINAL_CONSUMER_NAME = 'Consumidor final';

    public function __construct(private readonly CustomerRepository $customers)
    {
    }

    public function __invoke(CompanyRegistered $event): void
    {
        foreach ($this->customers->search($event->companyId, self::FINAL_CONSUMER_CODE) as $existing) {
            if (self::FINAL_CONSUMER_CODE === $existing->code()) {
                return;
            }
        }

        $this->customers->save(Customer::create(
            CustomerId::generate(),
            $event->companyId,
            self::FINAL_CONSUMER_CODE,
            self::FINAL_CONSUMER_NIF,
            self::FINAL_CONSUMER_NAME,
            null,
            null,
            null,
            'PT',
            null,
            null,
            null,
            true,
            $event->occurredAt,
        ));
    }
}
