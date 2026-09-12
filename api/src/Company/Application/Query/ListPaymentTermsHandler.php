<?php

declare(strict_types=1);

namespace App\Company\Application\Query;

use App\Company\Domain\PaymentTermsRepository;
use App\Shared\Domain\Company\CompanyContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * No permission gate, unlike the command handlers: any member of the
 * company needs to see the available payment terms when creating a
 * customer or supplier, not just whoever holds `company.manage` — same
 * "any member may read" precedent as `GetCompanyProfileHandler`.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class ListPaymentTermsHandler
{
    public function __construct(
        private readonly PaymentTermsRepository $paymentTerms,
        private readonly CompanyContext $companyContext,
    ) {
    }

    /**
     * @return list<PaymentTermsView>
     */
    public function __invoke(ListPaymentTerms $query): array
    {
        return array_map(
            PaymentTermsView::fromEntity(...),
            $this->paymentTerms->findAll($this->companyContext->companyId()),
        );
    }
}
