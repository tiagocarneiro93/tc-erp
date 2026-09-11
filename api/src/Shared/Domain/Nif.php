<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Portuguese NIF (Número de Identificação Fiscal), validated by the
 * standard modulus-11 check digit. Deliberately does not restrict which
 * leading digit is used: the legal list of valid taxpayer-category prefixes
 * is a separate, more volatile rule than the check-digit algorithm this
 * value object implements, and is not [VERIFY]-resolved here.
 */
final class Nif implements \Stringable
{
    private const LENGTH = 9;

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid NIF.', $value));
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        if (self::LENGTH !== \strlen($value) || 1 !== preg_match('/^\d{9}$/', $value)) {
            return false;
        }

        $sum = 0;
        for ($position = 0; $position < 8; ++$position) {
            $sum += (int) $value[$position] * (9 - $position);
        }

        $remainder = $sum % 11;
        $expectedCheckDigit = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $value[8] === $expectedCheckDigit;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
