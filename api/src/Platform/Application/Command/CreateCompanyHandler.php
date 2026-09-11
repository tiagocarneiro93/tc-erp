<?php

declare(strict_types=1);

namespace App\Platform\Application\Command;

use App\Platform\Domain\Company;
use App\Platform\Domain\CompanyRepository;
use App\Platform\Domain\Exception\CompanyAlreadyExists;
use App\Platform\Domain\Exception\InvalidNif;
use App\Platform\Domain\Membership;
use App\Platform\Domain\MembershipRepository;
use App\Shared\Domain\Audit\AuditLogger;
use App\Shared\Domain\Clock\Clock;
use App\Shared\Domain\Company\CompanyContext;
use App\Shared\Domain\Event\CompanyRegistered;
use App\Shared\Domain\Nif;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * §5.5: create company row -> owner membership -> audit entry -> a
 * `CompanyRegistered` event, one transaction. The event (dispatched last, on
 * `event.bus`, synchronously since nothing routes it to a transport) is how
 * other modules seed their own company-creation defaults (a
 * `company_profile` row from task 1.4, later a default warehouse/series)
 * without this handler depending on them (docs/decisions/0004) — replacing
 * the earlier "no default settings/warehouse/series here" note from task
 * 0.10, now that a module actually needs one.
 *
 * The new company's id comes from {@see CompanyContext}, not a field on
 * this command: the controller generates it and calls
 * `CompanyContext::set()` *before* dispatching, so the company.bus's
 * `doctrine_transaction` middleware opens the transaction with the id
 * already in place — otherwise the audit entry written below, in the same
 * transaction, would fail RLS's `WITH CHECK` (there would be no company row
 * yet to have resolved the context from).
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CreateCompanyHandler
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly MembershipRepository $memberships,
        private readonly AuditLogger $auditLogger,
        private readonly CompanyContext $companyContext,
        private readonly Clock $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(CreateCompany $command): void
    {
        try {
            $nif = Nif::fromString($command->nif);
        } catch (\InvalidArgumentException) {
            throw new InvalidNif();
        }

        if (null !== $this->companies->findByNif($nif)) {
            throw new CompanyAlreadyExists();
        }

        $companyId = $this->companyContext->companyId();
        $now = $this->clock->now();

        $this->companies->save(Company::register($companyId, $nif, $command->legalName, $now));
        $this->memberships->save(Membership::create($command->ownerId, $companyId, 'owner', $now));

        $this->auditLogger->log(
            'company.created',
            'Company',
            $companyId->toString(),
            ['legal_name' => $command->legalName, 'nif' => $nif->toString()],
            $command->ownerId->toString(),
            null,
            $command->ip,
            $command->userAgent,
        );

        $this->eventBus->dispatch(new CompanyRegistered($companyId, $nif, $command->legalName, $now));
    }
}
