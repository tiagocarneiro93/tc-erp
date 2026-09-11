<?php

declare(strict_types=1);

namespace App\Fiscal\Domain;

/**
 * Global reference data (technical-scope.md §6.6), seeded by a migration —
 * structural data, not legally variable (docs/plans/phase-1.md task 1.3).
 */
final class DocumentType
{
    public function __construct(
        private readonly string $code,
        private readonly string $saftSection,
        private readonly bool $signed,
        private readonly string $stockEffect,
        private readonly string $accountEffect,
        private readonly bool $requiresAtPriorCommunication,
    ) {
    }

    /**
     * FT|FS|FR|NC|ND|RG|GT|GR|GD|OR|PF|NE.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * SalesInvoices|MovementOfGoods|WorkingDocuments|Payments.
     */
    public function saftSection(): string
    {
        return $this->saftSection;
    }

    /**
     * False only for RG — receipts are not signed (Despacho 8632/2014 §1.1).
     */
    public function isSigned(): bool
    {
        return $this->signed;
    }

    /**
     * out|in|none.
     */
    public function stockEffect(): string
    {
        return $this->stockEffect;
    }

    /**
     * debit|credit|none.
     */
    public function accountEffect(): string
    {
        return $this->accountEffect;
    }

    /**
     * True for GT/GR/GD.
     */
    public function requiresAtPriorCommunication(): bool
    {
        return $this->requiresAtPriorCommunication;
    }
}
