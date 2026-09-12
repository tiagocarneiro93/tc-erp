<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

use App\Company\Domain\Exception\PaymentTermsNotFound;
use App\Company\Domain\PaymentTermsRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeactivatePaymentTermsHandler
{
    public function __construct(
        private readonly PaymentTermsRepository $paymentTerms,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(DeactivatePaymentTerms $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('company.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $paymentTerms = $this->paymentTerms->find($companyId, $command->paymentTermsId);

        if (null === $paymentTerms) {
            throw new PaymentTermsNotFound();
        }

        $paymentTerms->deactivate();
        $this->paymentTerms->save($paymentTerms);

        $this->auditLogger->log(
            'payment_terms.deactivated',
            'PaymentTerms',
            $command->paymentTermsId->toString(),
            [],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }
}
