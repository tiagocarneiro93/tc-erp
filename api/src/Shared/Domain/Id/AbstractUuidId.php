<?php

declare(strict_types=1);

namespace App\Shared\Domain\Id;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Base for per-aggregate typed IDs (e.g. `CompanyId`, `UserId`), backed by a
 * UUID v7 (time-ordered, index-friendly, safe to expose in the API —
 * technical-scope.md §6). Concrete IDs are `final class FooId extends
 * AbstractUuidId {}` with no extra code.
 */
abstract class AbstractUuidId implements \Stringable
{
    final private function __construct(private readonly UuidV7 $uuid)
    {
    }

    final public static function generate(): static
    {
        return new static(Uuid::v7());
    }

    final public static function fromString(string $value): static
    {
        $uuid = Uuid::fromString($value);

        if (!$uuid instanceof UuidV7) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a UUID v7.', $value));
        }

        return new static($uuid);
    }

    final public function equals(self $other): bool
    {
        return static::class === $other::class && $this->uuid->equals($other->uuid);
    }

    final public function toString(): string
    {
        return $this->uuid->toRfc4122();
    }

    final public function __toString(): string
    {
        return $this->toString();
    }
}
