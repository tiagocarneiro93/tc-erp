<?php

declare(strict_types=1);

namespace App\Platform\Domain;

interface MembershipRepository
{
    public function find(UserId $userId, CompanyId $companyId): ?Membership;

    /**
     * @return list<Membership>
     */
    public function findByUser(UserId $userId): array;

    public function save(Membership $membership): void;
}
