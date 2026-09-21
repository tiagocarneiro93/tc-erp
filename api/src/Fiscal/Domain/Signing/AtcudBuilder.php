<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Signing;

/**
 * Portaria 195/2020 Art. 3.º/4.º: the ATCUD (código único de documento) is
 * `{validationCode}-{sequentialNumber}` — this is the stored value (the
 * `documents.atcud`/`receipts.atcud` column, and the QR code's field H).
 * The `"ATCUD:"` prefix in {@see printed()} is a display-only convention
 * for the mention printed on paper, not part of the stored/computed
 * value.
 */
final class AtcudBuilder
{
    private function __construct()
    {
    }

    public static function build(string $validationCode, int $sequentialNumber): string
    {
        return $validationCode.'-'.$sequentialNumber;
    }

    public static function printed(string $atcud): string
    {
        return 'ATCUD:'.$atcud;
    }
}
