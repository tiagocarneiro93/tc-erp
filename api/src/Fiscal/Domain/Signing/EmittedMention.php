<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

/**
 * Despacho 8632/2014 §2.2.3: the mention printed/sent instead of
 * {@see PrintedHashMention} on any document that is not signed under
 * §2.1 — receipts (task 2.9), specifically. Verbatim example: "Emitido
 * por programa certificado n.º 0000/AT".
 */
final class EmittedMention
{
    private function __construct()
    {
    }

    public static function build(string $certificateNumber): string
    {
        return 'Emitido por programa certificado n.º '.$certificateNumber.'/AT';
    }
}
