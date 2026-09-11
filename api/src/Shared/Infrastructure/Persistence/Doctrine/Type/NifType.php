<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\Nif;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class NifType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 9;
        $column['fixed'] = true;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Nif
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Expected a string database value for Nif, got %s.', get_debug_type($value)));
        }

        return Nif::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Nif) {
            throw new \UnexpectedValueException(\sprintf('Expected an instance of %s, got %s.', Nif::class, get_debug_type($value)));
        }

        return $value->toString();
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::STRING;
    }
}
