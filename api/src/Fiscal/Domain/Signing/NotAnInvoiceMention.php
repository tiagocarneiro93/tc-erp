<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

/**
 * Despacho 8632/2014 §1.2: any document that is not an invoice or a
 * rectifying document of an invoice, and is presented to the customer
 * — including anything in SAF-T (PT) tables 4.2–4.4 — must carry this
 * mention. Scoped to {@see \App\Fiscal\Domain\DocumentType::mustDeclareItIsNotAnInvoice()}'s
 * WorkingDocuments types for now (task 2.8); GT/GR/GD (MovementOfGoods,
 * Phase 5) and RG (Payments, task 2.9 — which already prints its own
 * {@see EmittedMention} instead) are each that task's call to make, not
 * assumed here.
 */
final class NotAnInvoiceMention
{
    private function __construct()
    {
    }

    public static function build(): string
    {
        return 'Este documento não serve de fatura';
    }
}
