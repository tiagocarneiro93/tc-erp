<?php

declare(strict_types=1);

namespace App\Shared\Domain\Decimal;

use Brick\Math\BigDecimal;

/**
 * A percentage value as entered/stored (e.g. "23.00" for 23%), per
 * technical-scope.md §6.5 `tax_rates.percentage`.
 */
final class Percentage extends Decimal
{
    /**
     * @return BigDecimal the multiplier for a base amount (e.g. 23% -> 0.23),
     *                    exact since it's a multiplication by 0.01, never a division
     */
    public function asMultiplier(): BigDecimal
    {
        return $this->toBigDecimal()->multipliedBy('0.01');
    }
}
