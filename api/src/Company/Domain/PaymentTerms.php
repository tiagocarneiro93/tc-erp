<?php

declare(strict_types=1);

namespace App\Company\Domain;

use App\Shared\Domain\CompanyId;

/**
 * A company-managed catalog of payment terms (e.g. "Pronto Pagamento" / 0
 * dias, "30 dias") that customers and suppliers pick from, replacing a
 * free-typed number of days (task 1.5's original `payment_terms_days`).
 * Exactly one entry per company is `isDefault` over time — enforced by
 * `CreatePaymentTermsHandler`/`UpdatePaymentTermsHandler` unmarking the
 * previous default (via `PaymentTermsRepository::findDefault()`) whenever
 * one is (re)marked default, same pattern as `Inventory\Domain\Warehouse`.
 */
final class PaymentTerms
{
    private function __construct(
        private readonly PaymentTermsId $id,
        private readonly CompanyId $companyId,
        private string $name,
        private int $days,
        private bool $isDefault,
        private bool $active,
    ) {
    }

    public static function create(PaymentTermsId $id, CompanyId $companyId, string $name, int $days, bool $isDefault): self
    {
        return new self($id, $companyId, $name, $days, $isDefault, true);
    }

    public function update(string $name, int $days, bool $isDefault): void
    {
        $this->name = $name;
        $this->days = $days;
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

    public function id(): PaymentTermsId
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

    public function days(): int
    {
        return $this->days;
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
