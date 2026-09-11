<?php

declare(strict_types=1);

namespace App\Platform\Domain;

use App\Shared\Domain\CompanyId;

/**
 * Links a user to a company with a role (technical-scope.md §6.1); the
 * primary key is the (user_id, company_id) pair, not a surrogate id.
 */
final class Membership
{
    private function __construct(
        private readonly UserId $userId,
        private readonly CompanyId $companyId,
        private string $role,
        private string $status,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        UserId $userId,
        CompanyId $companyId,
        string $role,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            userId: $userId,
            companyId: $companyId,
            role: $role,
            status: 'active',
            createdAt: $now,
        );
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function changeRole(string $role): void
    {
        $this->role = $role;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
