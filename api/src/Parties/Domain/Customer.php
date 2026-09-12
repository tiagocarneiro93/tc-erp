<?php

declare(strict_types=1);

namespace App\Parties\Domain;

use App\Parties\Domain\Exception\FinalConsumerCustomerIsProtected;
use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.3. `nif` is a plain string, not the strict
 * {@see \App\Shared\Domain\Nif} value object: for a domestic customer it is
 * a check-digit-validated Portuguese NIF, but "foreign customers store
 * their country VAT ID" (§6.3's own note) in the same column, in whatever
 * format their country uses — validated conditionally by
 * `UpsertCustomerHandler`, not by this entity, since the rule depends on
 * `country`, not just the value's own shape.
 */
final class Customer
{
    private function __construct(
        private readonly CustomerId $id,
        private readonly CompanyId $companyId,
        private string $code,
        private string $nif,
        private string $name,
        private ?string $address,
        private ?string $postalCode,
        private ?string $city,
        private string $country,
        private ?string $email,
        private ?string $phone,
        private ?string $paymentTermsId,
        private ?string $priceListId,
        private bool $isFinalConsumer,
        private bool $active,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        CustomerId $id,
        CompanyId $companyId,
        string $code,
        string $nif,
        string $name,
        ?string $address,
        ?string $postalCode,
        ?string $city,
        string $country,
        ?string $email,
        ?string $phone,
        ?string $paymentTermsId,
        bool $isFinalConsumer,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $companyId,
            $code,
            $nif,
            $name,
            $address,
            $postalCode,
            $city,
            $country,
            $email,
            $phone,
            $paymentTermsId,
            null,
            $isFinalConsumer,
            true,
            $now,
            $now,
        );
    }

    public function update(
        string $code,
        string $nif,
        string $name,
        ?string $address,
        ?string $postalCode,
        ?string $city,
        string $country,
        ?string $email,
        ?string $phone,
        ?string $paymentTermsId,
        \DateTimeImmutable $now,
    ): void {
        if ($this->isFinalConsumer) {
            throw new FinalConsumerCustomerIsProtected();
        }

        $this->code = $code;
        $this->nif = $nif;
        $this->name = $name;
        $this->address = $address;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->country = $country;
        $this->email = $email;
        $this->phone = $phone;
        $this->paymentTermsId = $paymentTermsId;
        $this->updatedAt = $now;
    }

    public function deactivate(\DateTimeImmutable $now): void
    {
        if ($this->isFinalConsumer) {
            throw new FinalConsumerCustomerIsProtected();
        }

        $this->active = false;
        $this->updatedAt = $now;
    }

    public function id(): CustomerId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function nif(): string
    {
        return $this->nif;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function address(): ?string
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

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function paymentTermsId(): ?string
    {
        return $this->paymentTermsId;
    }

    /**
     * Unused until task 1.7 introduces `price_lists` — the column exists
     * now (technical-scope.md §6.3) but nothing sets or reads it yet.
     */
    public function priceListId(): ?string
    {
        return $this->priceListId;
    }

    public function isFinalConsumer(): bool
    {
        return $this->isFinalConsumer;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
