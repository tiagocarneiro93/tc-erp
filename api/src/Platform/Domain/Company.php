<?php

declare(strict_types=1);

namespace App\Platform\Domain;

use App\Shared\Domain\CompanyId;
use App\Shared\Domain\Nif;

final class Company
{
    private function __construct(
        private readonly CompanyId $id,
        private readonly Nif $nif,
        private string $legalName,
        private string $status,
        private ?string $plan,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(
        CompanyId $id,
        Nif $nif,
        string $legalName,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            nif: $nif,
            legalName: $legalName,
            status: 'active',
            plan: null,
            createdAt: $now,
        );
    }

    public function id(): CompanyId
    {
        return $this->id;
    }

    public function nif(): Nif
    {
        return $this->nif;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function plan(): ?string
    {
        return $this->plan;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
