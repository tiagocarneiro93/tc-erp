<?php

declare(strict_types=1);

namespace App\Shared\Domain\Decimal;

use Brick\Math\BigDecimal;

/**
 * Generic arbitrary-precision decimal (CLAUDE.md: never float for money,
 * quantities, prices, rates or percentages). {@see Money}, {@see Quantity}
 * and {@see Percentage} are the typed, fixed-scale variants; this class is
 * the shared building block and the type used for intermediate calculation
 * values (technical-scope.md §7.9.3 — 10 decimals, never rounded mid-calculation).
 */
class Decimal implements \Stringable
{
    final protected function __construct(private readonly BigDecimal $value)
    {
    }

    public static function fromString(string $value): static
    {
        return new static(BigDecimal::of($value));
    }

    public static function fromBigDecimal(BigDecimal $value): static
    {
        return new static($value);
    }

    public function toBigDecimal(): BigDecimal
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
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

    public function plus(self $other): static
    {
        return new static($this->value->plus($other->value));
    }

    public function minus(self $other): static
    {
        return new static($this->value->minus($other->value));
    }
}
