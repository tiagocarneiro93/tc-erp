<?php

declare(strict_types=1);

namespace App\Company\Application\Event;

use App\Company\Domain\CompanyProfile;
use App\Company\Domain\CompanyProfileRepository;
use App\Shared\Domain\Event\CompanyRegistered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * docs/decisions/0004: reacts to Platform's `CompanyRegistered` event
 * (dispatched by `CreateCompanyHandler`, still inside that command's
 * transaction and company context) by inserting the default
 * `company_profile` row — docs/plans/phase-1.md task 1.4.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class CreateDefaultCompanyProfileOnCompanyRegistered
{
    public function __construct(private readonly CompanyProfileRepository $profiles)
    {
    }

    public function __invoke(CompanyRegistered $event): void
    {
        if (null !== $this->profiles->find($event->companyId)) {
            return;
        }

        $this->profiles->save(CompanyProfile::createDefault($event->companyId, $event->nif, $event->legalName));
    }
}
