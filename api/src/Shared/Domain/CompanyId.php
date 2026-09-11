<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Id\AbstractUuidId;

/**
 * Lives in Shared, not Platform, because every company-scoped table in
 * every module carries a company_id (technical-scope.md §5.1) — unlike
 * UserId, which only Platform needs so far.
 */
final class CompanyId extends AbstractUuidId
{
}
