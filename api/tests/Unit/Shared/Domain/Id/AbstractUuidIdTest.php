<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Id;

use App\Platform\Domain\UserId;
use App\Shared\Domain\CompanyId;
use PHPUnit\Framework\TestCase;

final class AbstractUuidIdTest extends TestCase
{
    public function testGenerateProducesAUniqueRoundTrippableId(): void
    {
        $id = CompanyId::generate();
        $roundTripped = CompanyId::fromString($id->toString());

        self::assertTrue($id->equals($roundTripped));
        self::assertNotSame($id->toString(), CompanyId::generate()->toString());
    }

    public function testDifferentIdTypesAreNeverEqualEvenWithTheSameUuid(): void
    {
        $uuid = CompanyId::generate()->toString();

        self::assertFalse(CompanyId::fromString($uuid)->equals(UserId::fromString($uuid)));
    }

    public function testFromStringRejectsANonV7Uuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // A UUID v4, not v7.
        CompanyId::fromString('a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11');
    }

    public function testFromStringRejectsAnInvalidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CompanyId::fromString('not-a-uuid');
    }
}
