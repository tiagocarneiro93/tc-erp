<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.10. Exactly one warehouse per company is `isDefault`
 * over time — enforced by `CreateWarehouseHandler`/`UpdateWarehouseHandler`
 * unmarking the previous default (via `WarehouseRepository::findDefault()`)
 * whenever a warehouse is (re)marked default, not by a DB constraint.
 */
final class Warehouse
{
    private function __construct(
        private readonly WarehouseId $id,
        private readonly CompanyId $companyId,
        private string $code,
        private string $name,
        private ?string $address,
        private bool $isDefault,
        private bool $active,
    ) {
    }

    public static function create(
        WarehouseId $id,
        CompanyId $companyId,
        string $code,
        string $name,
        ?string $address,
        bool $isDefault,
    ): self {
        return new self($id, $companyId, $code, $name, $address, $isDefault, true);
    }

    public function update(string $code, string $name, ?string $address, bool $isDefault): void
    {
        $this->code = $code;
        $this->name = $name;
        $this->address = $address;
        $this->isDefault = $isDefault;
    }

    public function unmarkAsDefault(): void
    {
        $this->isDefault = false;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function id(): WarehouseId
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

    public function name(): string
    {
        return $this->name;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function active(): bool
    {
        return $this->active;
    }
}
