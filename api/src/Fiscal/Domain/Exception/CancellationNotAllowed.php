<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * docs/plans/phase-2.md task 2.10, `docs/legal/civa-extracts.md`'s "What
 * this resolves" note: cancellation (SAF-T `InvoiceStatus = A`) is only
 * legitimate for a document that never had external effect. Once a
 * rectifying NC exists (Despacho 8632/2014 §3.3.8 — cancel that first) or
 * the document may already have reached the AT (CIVA Art. 29.º §7 — any
 * correction, for any reason, must go through a rectifying document
 * instead), only NC/ND applies, never a plain cancellation.
 */
final class CancellationNotAllowed extends \DomainException implements ProblemDetails
{
    public static function documentNotActive(string $status): self
    {
        return new self(\sprintf('Only a document with status "N" can be cancelled (current status: "%s").', $status));
    }

    public static function alreadyRectified(): self
    {
        return new self('This document already has an active credit note — cancel that first (Despacho 8632/2014 §3.3.8).');
    }

    public static function mayAlreadyHaveReachedTheCustomer(): self
    {
        return new self('This document may already have been communicated to the AT — any correction now must be a credit/debit note, never a cancellation (CIVA Art. 29.º §7).');
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function problemType(): string
    {
        return 'cancellation-not-allowed';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
