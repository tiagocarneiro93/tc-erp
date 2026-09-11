<?php

declare(strict_types=1);

namespace App\Shared\Domain\Decimal;

use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;

/**
 * Money at 2 decimal places, EUR only in v1 (technical-scope.md §14.2
 * decision 11 and §7.9.3: "money that people see, pay or sign is always 2
 * decimals"). Rejects more precision than that rather than silently
 * rounding; round explicitly (e.g. in `PriceCalculator`, Phase 2) before
 * constructing one.
 */
final class Money implements \Stringable
{
    private const CURRENCY = 'EUR';

    private function __construct(private readonly BrickMoney $value)
    {
    }

    public static function fromString(string $amount): self
    {
        return new self(BrickMoney::of($amount, self::CURRENCY, roundingMode: RoundingMode::Unnecessary));
    }

    public static function zero(): self
    {
        return new self(BrickMoney::of(0, self::CURRENCY));
    }

    public function toString(): string
    {
        return $this->value->getAmount()->__toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
    }

    public function compareTo(self $other): int
    {
        return $this->value->compareTo($other->value);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function isNegative(): bool
    {
        return $this->value->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->value->isPositive();
    }

    public function plus(self $other): self
    {
        return new self($this->value->plus($other->value));
    }

    public function minus(self $other): self
    {
        return new self($this->value->minus($other->value));
    }
}
