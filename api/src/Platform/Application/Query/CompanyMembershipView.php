<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

final class CompanyMembershipView
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly string $companyId,
        public readonly string $legalName,
        public readonly string $role,
        public readonly array $permissions,
    ) {
    }
}
