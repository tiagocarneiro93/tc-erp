<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.4: a tree via `parentId`, cycle-free — the actual
 * cycle check needs a repository to walk ancestors, so it lives in
 * `UpdateProductFamilyHandler`/`CreateProductFamilyHandler`, not here.
 */
final class ProductFamily
{
    private function __construct(
        private readonly ProductFamilyId $id,
        private readonly CompanyId $companyId,
        private string $name,
        private ?ProductFamilyId $parentId,
    ) {
    }

    public static function create(ProductFamilyId $id, CompanyId $companyId, string $name, ?ProductFamilyId $parentId): self
    {
        return new self($id, $companyId, $name, $parentId);
    }

    public function rename(string $name, ?ProductFamilyId $parentId): void
    {
        $this->name = $name;
        $this->parentId = $parentId;
    }

    public function id(): ProductFamilyId
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

    public function parentId(): ?ProductFamilyId
    {
        return $this->parentId;
    }
}
