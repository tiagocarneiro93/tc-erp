<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\CompanyId;

/**
 * technical-scope.md §6.4. `defaultIncludesVat` is the default pricing mode
 * for new prices added to this list — document pricing-mode defaults
 * (§7.9.8: "taken from the price list/customer") are a Phase 2 concern.
 */
final class PriceList
{
    public function __construct(
        private readonly PriceListId $id,
        private readonly CompanyId $companyId,
        private readonly string $name,
        private readonly bool $defaultIncludesVat,
    ) {
    }

    public function id(): PriceListId
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

    public function defaultIncludesVat(): bool
    {
        return $this->defaultIncludesVat;
    }
}
