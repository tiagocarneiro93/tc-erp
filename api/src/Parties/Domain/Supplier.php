<?php

declare(strict_types=1);

namespace App\Parties\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.3. See {@see Customer}'s docblock for why `nif` is
 * a plain string rather than the strict {@see \App\Shared\Domain\Nif} value
 * object.
 */
final class Supplier
{
    private function __construct(
        private readonly SupplierId $id,
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
        private bool $active,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        SupplierId $id,
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
        $this->active = false;
        $this->updatedAt = $now;
    }

    public function id(): SupplierId
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
