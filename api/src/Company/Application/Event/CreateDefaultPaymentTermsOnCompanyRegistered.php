<?php

declare(strict_types=1);

namespace App\Company\Application\Event;

use App\Company\Domain\PaymentTerms;
use App\Company\Domain\PaymentTermsId;
use App\Company\Domain\PaymentTermsRepository;
use App\Shared\Domain\Event\CompanyRegistered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every new company gets one seeded payment term, "Pronto Pagamento" (0
 * dias), marked default — same event-driven pattern as Company's own
 * default-profile seeding, Parties' "Consumidor final" customer, and
 * Inventory's default warehouse (docs/decisions/0004).
 *
 * Idempotent: `app:seed` backfills companies that predate this listener by
 * re-dispatching `CompanyRegistered` rather than depending on this module's
 * domain directly, which Deptrac forbids from `Platform\Infrastructure`.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class CreateDefaultPaymentTermsOnCompanyRegistered
{
    private const DEFAULT_NAME = 'Pronto Pagamento';
    private const DEFAULT_DAYS = 0;

    public function __construct(private readonly PaymentTermsRepository $paymentTerms)
    {
    }

    public function __invoke(CompanyRegistered $event): void
    {
        if (null !== $this->paymentTerms->findDefault($event->companyId)) {
            return;
        }

        $this->paymentTerms->save(PaymentTerms::create(
            PaymentTermsId::generate(),
            $event->companyId,
            self::DEFAULT_NAME,
            self::DEFAULT_DAYS,
            true,
        ));
    }
}
