<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

use App\Company\Domain\PaymentTerms;
use App\Company\Domain\PaymentTermsRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reuses `company.manage` (ADR 0002: "Edit company profile, fiscal
 * settings, AT credentials") — payment terms are company settings, not a
 * customers/products-specific concern, even though customers/suppliers
 * are what actually reference them.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CreatePaymentTermsHandler
{
    public function __construct(
        private readonly PaymentTermsRepository $paymentTerms,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(CreatePaymentTerms $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('company.manage', $companyId)) {
            throw new PermissionDenied();
        }

        if ($command->isDefault) {
            $this->unmarkCurrentDefault($companyId);
        }

        $this->paymentTerms->save(PaymentTerms::create(
            $command->paymentTermsId,
            $companyId,
            $command->name,
            $command->days,
            $command->isDefault,
        ));

        $this->auditLogger->log(
            'payment_terms.created',
            'PaymentTerms',
            $command->paymentTermsId->toString(),
            ['name' => $command->name, 'days' => $command->days],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    private function unmarkCurrentDefault(CompanyId $companyId): void
    {
        $currentDefault = $this->paymentTerms->findDefault($companyId);

        if (null !== $currentDefault) {
            $currentDefault->unmarkAsDefault();
            $this->paymentTerms->save($currentDefault);
        }
    }
}
