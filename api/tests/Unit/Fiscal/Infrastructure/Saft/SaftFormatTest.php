<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Infrastructure\Saft;

use App\Fiscal\Infrastructure\Saft\SaftFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SaftFormatTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function amounts(): iterable
    {
        yield 'whole euros keep two decimals' => ['10.000000', '10.00'];
        yield 'cents' => ['12.300000', '12.30'];
        yield 'sub-cent precision is kept' => ['0.123400', '0.1234'];
        yield 'AT sample precision' => ['165.016600', '165.0166'];
        yield 'zero' => ['0', '0.00'];
        yield 'rounds half up at six decimals' => ['1.0000005', '1.000001'];
        yield 'large' => ['1234567.890000', '1234567.89'];
    }

    #[DataProvider('amounts')]
    public function testAmountsDropTrailingZerosButNeverBelowTwoDecimals(string $stored, string $expected): void
    {
        self::assertSame($expected, SaftFormat::amount($stored));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function quantities(): iterable
    {
        yield 'whole' => ['1.000000', '1'];
        yield 'fraction' => ['35.269000', '35.269'];
        yield 'ten' => ['10.000000', '10'];
        yield 'hundred' => ['100.000000', '100'];
        yield 'small' => ['0.500000', '0.5'];
    }

    #[DataProvider('quantities')]
    public function testQuantities(string $stored, string $expected): void
    {
        self::assertSame($expected, SaftFormat::quantity($stored));
    }

    public function testDatesAndTimesAreUtcWithoutAnOffset(): void
    {
        $value = new \DateTimeImmutable('2026-03-05T23:30:15-02:00');

        self::assertSame('2026-03-06', SaftFormat::date($value));
        self::assertSame('2026-03-06T01:30:15', SaftFormat::dateTime($value));
        self::assertSame(3, SaftFormat::period($value));
    }

    public function testTextIsCutToTheSchemasMaximumAndNeverEmpty(): void
    {
        self::assertSame('abc', SaftFormat::text('  abc  ', 10));
        self::assertSame('abcde', SaftFormat::text('abcdefgh', 5));
        self::assertSame('Desconhecido', SaftFormat::text('   ', 10));
        self::assertSame('Desconhecido', SaftFormat::text(null, 10));
        self::assertSame('çãõé', SaftFormat::text('çãõéxx', 4), 'Cuts characters, not bytes.');
    }

    public function testIdsFitTheThirtyCharacterLimitAndAreStable(): void
    {
        $uuid = '0192e0f0-1234-7abc-8def-0123456789ab';

        self::assertSame('0192e0f012347abc8def0123456789', SaftFormat::shortId($uuid));
        self::assertSame(30, \strlen(SaftFormat::shortId($uuid)));
        self::assertSame(SaftFormat::shortId($uuid), SaftFormat::customerId($uuid));
        self::assertSame('Consumidor final', SaftFormat::customerId(null));
        self::assertSame('Consumidor final', SaftFormat::customerId(''));
    }

    public function testPaymentMethodsMapOntoTheSchemasMechanisms(): void
    {
        self::assertSame('NU', SaftFormat::paymentMechanism('cash'));
        self::assertSame('TB', SaftFormat::paymentMechanism('Transfer'));
        self::assertSame('CC', SaftFormat::paymentMechanism('card'));
        self::assertSame('MB', SaftFormat::paymentMechanism('multibanco'));
        self::assertSame('OU', SaftFormat::paymentMechanism('bitcoin'));
    }
}
