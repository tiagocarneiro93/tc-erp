<?php

declare(strict_types=1);

namespace App\Parties\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.3: extra delivery/loading addresses for a customer
 * or supplier, beyond their main one. `partyType`/`partyId` point at either
 * a {@see Customer} or a {@see Supplier} (polymorphic, so `partyId` is a
 * plain string rather than either typed id). Schema and repository only in
 * task 1.5 — no endpoint yet, since the CRUD this task's plan calls for is
 * customers/suppliers themselves; a later task wires up management UI for
 * extra addresses when something needs one.
 */
final class Address
{
    public function __construct(
        private readonly AddressId $id,
        private readonly CompanyId $companyId,
        private readonly string $partyType,
        private readonly string $partyId,
        private string $label,
        private string $address,
        private ?string $postalCode,
        private ?string $city,
        private string $country,
    ) {
    }

    public function id(): AddressId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    /**
     * "customer"|"supplier".
     */
    public function partyType(): string
    {
        return $this->partyType;
    }

    public function partyId(): string
    {
        return $this->partyId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function country(): string
    {
        return $this->country;
    }
}
