<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Exception;

use App\Shared\Domain\Exception\ProblemDetails;

/**
 * Despacho 8632/2014 §3.3.7: "A criação de notas de crédito relativas a
 * documentos anteriormente anulados ou já totalmente retificados" is not
 * permitted.
 */
final class CreditNoteNotAllowed extends \DomainException implements ProblemDetails
{
    public static function documentIsCancelled(): self
    {
        return new self('Cannot issue a credit note against a cancelled document (Despacho 8632/2014 §3.3.7).');
    }

    public static function documentIsFullyRectified(): self
    {
        return new self('Cannot issue a credit note against an already fully rectified document (Despacho 8632/2014 §3.3.7).');
    }

    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function problemType(): string
    {
        return 'credit-note-not-allowed';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
