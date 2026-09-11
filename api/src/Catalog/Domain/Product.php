<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.4. `lastCost`/`averageCost` are always null in
 * Phase 1 — no command sets them; Phase 5 (Inventory) populates them. Left
 * as plain decimal strings rather than a typed value object: nothing
 * calculates with them yet, and `Money` (2 decimals, technical-scope.md
 * §7.9.3) is the wrong scale for a per-unit cost — Phase 5 picks the right
 * representation once it has an actual calculation to support.
 */
final class Product
{
    /**
     * SAF-T ProductType (technical-scope.md §6.4): P|S|O|E|I, as given in
     * the scope itself.
     */
    private const VALID_TYPES = ['P', 'S', 'O', 'E', 'I'];

    /**
     * `kit` is structurally allowed from this task on, but has no
     * components until task 1.8 builds `product_components` (§7.10).
     */
    private const VALID_KINDS = ['simple', 'kit'];

    private function __construct(
        private readonly ProductId $id,
        private readonly CompanyId $companyId,
        private string $code,
        private string $description,
        private string $type,
        private string $kind,
        private string $unitCode,
        private ?string $barcode,
        private ?ProductFamilyId $familyId,
        private string $taxRateId,
        private ?string $exemptionReasonCode,
        private bool $trackStock,
        private bool $active,
        private readonly ?string $lastCost,
        private readonly ?string $averageCost,
    ) {
    }

    public static function isValidType(string $type): bool
    {
        return \in_array($type, self::VALID_TYPES, true);
    }

    public static function isValidKind(string $kind): bool
    {
        return \in_array($kind, self::VALID_KINDS, true);
    }

    public static function create(
        ProductId $id,
        CompanyId $companyId,
        string $code,
        string $description,
        string $type,
        string $kind,
        string $unitCode,
        ?string $barcode,
        ?ProductFamilyId $familyId,
        string $taxRateId,
        ?string $exemptionReasonCode,
        bool $trackStock,
    ): self {
        return new self(
            $id,
            $companyId,
            $code,
            $description,
            $type,
            $kind,
            $unitCode,
            $barcode,
            $familyId,
            $taxRateId,
            $exemptionReasonCode,
            $trackStock,
            true,
            null,
            null,
        );
    }

    public function update(
        string $code,
        string $description,
        string $type,
        string $unitCode,
        ?string $barcode,
        ?ProductFamilyId $familyId,
        string $taxRateId,
        ?string $exemptionReasonCode,
        bool $trackStock,
    ): void {
        $this->code = $code;
        $this->description = $description;
        $this->type = $type;
        $this->unitCode = $unitCode;
        $this->barcode = $barcode;
        $this->familyId = $familyId;
        $this->taxRateId = $taxRateId;
        $this->exemptionReasonCode = $exemptionReasonCode;
        $this->trackStock = $trackStock;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function id(): ProductId
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

    public function description(): string
    {
        return $this->description;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * "simple"|"kit" — fixed at creation, not editable via `update()`:
     * turning a product into/out of a kit after it may already have
     * components (task 1.8) or documents referencing it is a business
     * decision the scope doesn't cover in v1.
     */
    public function kind(): string
    {
        return $this->kind;
    }

    public function unitCode(): string
    {
        return $this->unitCode;
    }

    public function barcode(): ?string
    {
        return $this->barcode;
    }

    public function familyId(): ?ProductFamilyId
    {
        return $this->familyId;
    }

    public function taxRateId(): string
    {
        return $this->taxRateId;
    }

    public function exemptionReasonCode(): ?string
    {
        return $this->exemptionReasonCode;
    }

    public function trackStock(): bool
    {
        return $this->trackStock;
    }

    public function active(): bool
    {
        return $this->active;
    }

    /**
     * Always null in Phase 1 — Phase 5 (Inventory) populates it.
     */
    public function lastCost(): ?string
    {
        return $this->lastCost;
    }

    /**
     * Always null in Phase 1 — Phase 5 (Inventory) populates it.
     */
    public function averageCost(): ?string
    {
        return $this->averageCost;
    }
}
