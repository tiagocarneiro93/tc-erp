<?php

declare(strict_types=1);

namespace App\Shared\Domain\Decimal;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A quantity, stored at scale 6 (technical-scope.md §6: `NUMERIC(19,6)`).
 * Rejects more than 6 decimal places rather than silently rounding.
 */
final class Quantity extends Decimal
{
    private const SCALE = 6;

    public static function fromString(string $value): static
    {
        return new static(BigDecimal::of($value)->toScale(self::SCALE, RoundingMode::Unnecessary));
    }
}
