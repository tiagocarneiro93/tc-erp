<?php

declare(strict_types=1);

namespace App\Platform\Application\Query;

final class MeView
{
    /**
     * @param list<CompanyMembershipView> $companies
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly string $name,
        public readonly bool $mustChangePassword,
        public readonly array $companies,
    ) {
    }
}
