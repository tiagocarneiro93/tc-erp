<?php

declare(strict_types=1);

namespace App\Tax\Domain;

use App\Shared\Domain\Decimal\Decimal;
use App\Shared\Domain\Decimal\Percentage;

/**
 * A single discount applied to a line, in the order the line lists them
 * (technical-scope.md §14.2 decision 9: percentage, cascading percentages,
 * or a fixed amount per line — a line may carry several of these, applied
 * one after another to the running amount). Never both a percentage and an
 * amount in the same instance: two instances express "10% then 5% then
 * 2.00 off" as three `LineDiscount`s, not one.
 */
final class LineDiscount
{
    private function __construct(
        private readonly ?Percentage $percentage,
        private readonly ?Decimal $fixedAmount,
    ) {
    }

    public static function percentage(Percentage $percentage): self
    {
        return new self($percentage, null);
    }

    public static function fixedAmount(Decimal $amount): self
    {
        return new self(null, $amount);
    }

    public function isPercentage(): bool
    {
        return null !== $this->percentage;
    }

    public function percentageValue(): Percentage
    {
        if (null === $this->percentage) {
            throw new \LogicException('This discount is a fixed amount, not a percentage.');
        }

        return $this->percentage;
    }

    public function fixedAmountValue(): Decimal
    {
        if (null === $this->fixedAmount) {
            throw new \LogicException('This discount is a percentage, not a fixed amount.');
        }

        return $this->fixedAmount;
    }
}
