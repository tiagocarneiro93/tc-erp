<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Postgres `JSONB` (indexable, preferred over plain `JSON` for every JSONB
 * column in technical-scope.md §6). Reuses JsonType's PHP<->string
 * conversion, only the column DDL differs.
 */
final class JsonbType extends JsonType
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSONB';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Expected a string database value for jsonb, got %s.', get_debug_type($value)));
        }

        $decoded = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \UnexpectedValueException(\sprintf('Could not convert database value "%s" to a JSONB array.', $value));
        }

        return $decoded;
    }
}
