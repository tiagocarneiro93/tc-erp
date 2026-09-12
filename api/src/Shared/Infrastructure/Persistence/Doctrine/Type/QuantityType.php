<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\Decimal\Quantity;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Stored as NUMERIC(19,6), matching {@see Quantity}'s own fixed scale.
 * First persisted use is `product_components.quantity` (task 1.8).
 */
final class QuantityType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC(19, 6)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Quantity
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Expected a string database value for Quantity, got %s.', get_debug_type($value)));
        }

        return Quantity::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Quantity) {
            throw new \UnexpectedValueException(\sprintf('Expected an instance of %s, got %s.', Quantity::class, get_debug_type($value)));
        }

        return $value->toString();
    }
}
