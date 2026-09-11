<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\Nif;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NifTest extends TestCase
{
    #[DataProvider('validNifs')]
    public function testValidNifsAreAccepted(string $nif): void
    {
        self::assertTrue(Nif::isValid($nif));
        self::assertSame($nif, Nif::fromString($nif)->toString());
    }

    /** @return iterable<string, array{string}> */
    public static function validNifs(): iterable
    {
        // Check digit worked out by hand from the modulus-11 algorithm.
        yield 'sequential digits' => ['123456789'];
        // The seeded "Consumidor final" customer (technical-scope.md §6.3).
        yield 'consumidor final' => ['999999990'];
    }

    #[DataProvider('invalidNifs')]
    public function testInvalidNifsAreRejected(string $nif): void
    {
        self::assertFalse(Nif::isValid($nif));
        $this->expectException(\InvalidArgumentException::class);
        Nif::fromString($nif);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNifs(): iterable
    {
        yield 'wrong check digit' => ['123456788'];
        yield 'too short' => ['12345678'];
        yield 'too long' => ['1234567890'];
        yield 'non-numeric' => ['12345678A'];
        yield 'empty' => [''];
    }
}
