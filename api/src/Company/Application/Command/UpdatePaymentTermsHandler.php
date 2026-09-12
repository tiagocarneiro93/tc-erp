<?php

declare(strict_types=1);

namespace App\Company\Application\Command;

use App\Company\Domain\Exception\PaymentTermsNotFound;
use App\Company\Domain\PaymentTermsRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Exception\PermissionDenied;
use App\Shared\Domain\Security\PermissionChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdatePaymentTermsHandler
{
    public function __construct(
        private readonly PaymentTermsRepository $paymentTerms,
        private readonly PermissionChecker $permissionChecker,
        private readonly CompanyContext $companyContext,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(UpdatePaymentTerms $command): void
    {
        $companyId = $this->companyContext->companyId();

        if (!$this->permissionChecker->isGranted('company.manage', $companyId)) {
            throw new PermissionDenied();
        }

        $paymentTerms = $this->paymentTerms->find($companyId, $command->paymentTermsId);

        if (null === $paymentTerms) {
            throw new PaymentTermsNotFound();
        }

        if ($command->isDefault) {
            $this->unmarkCurrentDefault($companyId, $command->paymentTermsId->toString());
        }

        $paymentTerms->update($command->name, $command->days, $command->isDefault);
        $this->paymentTerms->save($paymentTerms);

        $this->auditLogger->log(
            'payment_terms.updated',
            'PaymentTerms',
            $command->paymentTermsId->toString(),
            ['name' => $command->name, 'days' => $command->days],
            $command->actingUserId,
            null,
            $command->ip,
            $command->userAgent,
        );
    }

    private function unmarkCurrentDefault(CompanyId $companyId, string $exceptPaymentTermsId): void
    {
        $currentDefault = $this->paymentTerms->findDefault($companyId);

        if (null !== $currentDefault && $currentDefault->id()->toString() !== $exceptPaymentTermsId) {
            $currentDefault->unmarkAsDefault();
            $this->paymentTerms->save($currentDefault);
        }
    }
}
