<?php

declare(strict_types=1);

namespace App\Tax\Domain;

/**
 * technical-scope.md §7.9.2 principle 3: `net` anchors on VAT-exclusive
 * amounts (VAT derived), `gross` anchors on VAT-inclusive amounts (net and
 * VAT derived) so the total always equals what the customer saw.
 */
enum PricingMode: string
{
    case Net = 'net';
    case Gross = 'gross';
}
