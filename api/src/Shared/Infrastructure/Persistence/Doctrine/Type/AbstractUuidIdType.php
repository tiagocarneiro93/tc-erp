<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\Id\AbstractUuidId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Base Doctrine DBAL type for {@see AbstractUuidId} subclasses, stored as a
 * native Postgres `UUID` column. A concrete typed ID needs only its own
 * one-line subclass (see {@see UserIdType}) plus a registration line in
 * config/packages/doctrine.yaml.
 */
abstract class AbstractUuidIdType extends Type
{
    /**
     * @return class-string<AbstractUuidId>
     */
    abstract protected function idClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AbstractUuidId
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Expected a string database value for %s, got %s.', $this->idClass(), get_debug_type($value)));
        }

        $idClass = $this->idClass();

        return $idClass::fromString($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof AbstractUuidId) {
            throw new \UnexpectedValueException(\sprintf('Expected an instance of %s, got %s.', AbstractUuidId::class, get_debug_type($value)));
        }

        return $value->toString();
    }
}
