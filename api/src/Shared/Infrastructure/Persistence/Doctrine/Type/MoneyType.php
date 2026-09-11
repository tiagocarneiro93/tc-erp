<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\Decimal\Money;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Stored as NUMERIC(19,2): EUR only in v1, 2 decimals (Money's own
 * docblock, technical-scope.md §14.2 decision 11). First persisted use is
 * `company_profile.share_capital` (task 1.4).
 */
final class MoneyType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC(19, 2)';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Money
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Expected a string database value for Money, got %s.', get_debug_type($value)));
        }

        return Money::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Money) {
            throw new \UnexpectedValueException(\sprintf('Expected an instance of %s, got %s.', Money::class, get_debug_type($value)));
        }

        return $value->toString();
    }
}
