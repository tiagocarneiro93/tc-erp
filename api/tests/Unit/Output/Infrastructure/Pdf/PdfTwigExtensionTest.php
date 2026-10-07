<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Pdf;

use App\Output\Infrastructure\Pdf\PdfTwigExtension;
use App\Output\Infrastructure\Pdf\QrCodeImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PdfTwigExtensionTest extends TestCase
{
    private PdfTwigExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new PdfTwigExtension(new QrCodeImage());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function amounts(): iterable
    {
        yield 'small' => ['9.5', '9,50&nbsp;€'];
        yield 'hundreds' => ['123.000000', '123,00&nbsp;€'];
        yield 'thousands' => ['1234.5', '1&nbsp;234,50&nbsp;€'];
        yield 'millions' => ['1234567.891', '1&nbsp;234&nbsp;567,89&nbsp;€'];
        yield 'exactly three digits' => ['100', '100,00&nbsp;€'];
        yield 'rounds half up' => ['0.005', '0,01&nbsp;€'];
        yield 'negative' => ['-1234.5', '-1&nbsp;234,50&nbsp;€'];
        yield 'zero' => ['0', '0,00&nbsp;€'];
    }

    #[DataProvider('amounts')]
    public function testMoneyIsPtPtFormatted(string $stored, string $expected): void
    {
        self::assertSame($expected, $this->extension->money($stored));
    }

    public function testPricesKeepTwoToSixDecimals(): void
    {
        self::assertSame('12,50', $this->extension->price('12.500000'));
        self::assertSame('0,1234', $this->extension->price('0.123400'));
        self::assertSame('1&nbsp;000,00', $this->extension->price('1000'));
    }

    public function testQuantitiesDropTrailingZeros(): void
    {
        self::assertSame('2', $this->extension->quantity('2.000000'));
        self::assertSame('0,5', $this->extension->quantity('0.500000'));
        self::assertSame('1&nbsp;000', $this->extension->quantity('1000'));
        self::assertSame('35,269', $this->extension->quantity('35.269000'));
    }

    public function testPercentagesHaveTwoDecimals(): void
    {
        self::assertSame('23,00%', $this->extension->percent('23'));
        self::assertSame('6,00%', $this->extension->percent('6.00'));
    }

    public function testDatesAreIso(): void
    {
        self::assertSame('2026-03-05', $this->extension->isoDate(new \DateTimeImmutable('2026-03-05T23:59:59+00:00')));
    }
}
