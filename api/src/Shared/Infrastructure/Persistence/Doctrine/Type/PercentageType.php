<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\Decimal\Percentage;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Stored as NUMERIC(5,2): percentages never need more than 2 decimals or
 * exceed 100 (CLAUDE.md — never float for rates or percentages).
 */
final class PercentageType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC(5, 2)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Percentage
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Expected a string database value for Percentage, got %s.', get_debug_type($value)));
        }

        return Percentage::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Percentage) {
            throw new \UnexpectedValueException(\sprintf('Expected an instance of %s, got %s.', Percentage::class, get_debug_type($value)));
        }

        return $value->toString();
    }
}
