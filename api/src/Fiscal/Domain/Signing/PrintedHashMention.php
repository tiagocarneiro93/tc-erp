<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

/**
 * Despacho 8632/2014 §2.2.2: the mention every printed/emailed document
 * must carry — the 1.ª, 11.ª, 21.ª and 31.ª characters of the signature
 * (`Hash`), concatenated, followed by a hyphen and "Processado por
 * programa certificado n.º <cert>/AT". Verbatim example from the
 * Despacho: "AxAx-Processado por programa certificado n.º 0000/AT".
 *
 * This is the `hash_control` column's value (migration
 * `Version20260921110000.php`) for every signed document type; receipts
 * use {@see EmittedMention} instead (Despacho §2.2.3), since they are
 * never signed.
 */
final class PrintedHashMention
{
    private function __construct()
    {
    }

    public static function build(string $hash, string $certificateNumber): string
    {
        return self::fourCharacters($hash).'-Processado por programa certificado n.º '.$certificateNumber.'/AT';
    }

    /**
     * The same 1.ª/11.ª/21.ª/31.ª-character extraction used here also
     * fills the QR code's field Q (`at-qrcode-spec.pdf` §4: "Preencher de
     * acordo com a Portaria n.º 363/2010, de 23 de junho").
     */
    public static function fourCharacters(string $hash): string
    {
        return $hash[0].$hash[10].$hash[20].$hash[30];
    }
}
