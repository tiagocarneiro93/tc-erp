<?php

declare(strict_types=1);

namespace App\Platform\Domain;

/**
 * Structure only for now (task 0.7); real issuance is Phase 1 (§9.2:
 * "company-scoped API tokens with scopes").
 *
 * Has a `companyId` column but, despite that, is a *global* table with no
 * RLS: authenticating a request by token is what establishes which company
 * a request acts as, so it must be queryable before any company context
 * (and RLS policy) exists — the reverse of every other company-scoped
 * table. technical-scope.md §5.1 lists "API tokens" under global tables;
 * §6.1's column listing (which includes `company_id`) is read here as "the
 * token's target company", not as an RLS discriminator.
 *
 * @phpstan-type Scopes list<string>
 */
final class ApiToken
{
    /**
     * @param Scopes $scopes
     */
    public function __construct(
        private readonly ApiTokenId $id,
        private readonly CompanyId $companyId,
        private readonly string $name,
        private readonly string $tokenHash,
        private readonly array $scopes,
        private readonly ?\DateTimeImmutable $lastUsedAt,
        private readonly ?\DateTimeImmutable $expiresAt,
        private readonly ?\DateTimeImmutable $revokedAt,
    ) {
    }

    public function id(): ApiTokenId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    /**
     * @return Scopes
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function lastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
