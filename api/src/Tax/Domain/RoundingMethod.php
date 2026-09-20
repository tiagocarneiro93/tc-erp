<?php

declare(strict_types=1);

namespace App\Tax\Domain;

/**
 * technical-scope.md §7.9.5: both methods round to 2 decimals; they differ
 * in *where* that rounding happens. `PerLine` rounds each line to 2
 * decimals first and sums the already-rounded values into each tax-key
 * group. `PerGroup` sums lines at full precision per tax key first, then
 * rounds once per group — so an individual line has no authoritative
 * rounded value of its own under this method, only the group (and hence
 * the document total) does.
 */
enum RoundingMethod: string
{
    case PerLine = 'per_line';
    case PerGroup = 'per_group';
}
