<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;

/**
 * Dispatched on `event.bus` by `Platform\Application\Command\CreateCompanyHandler`
 * right after the company row is inserted, so other modules can react
 * without Platform depending on them (docs/decisions/0004). Plain data, no
 * framework dependency, same as any other Domain class.
 */
final class CompanyRegistered
{
    public function __construct(
        public readonly CompanyId $companyId,
        public readonly Nif $nif,
        public readonly string $legalName,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}
