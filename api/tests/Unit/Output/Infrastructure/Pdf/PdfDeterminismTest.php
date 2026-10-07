<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Pdf;

use App\Output\Infrastructure\Pdf\PdfDeterminism;
use PHPUnit\Framework\TestCase;

final class PdfDeterminismTest extends TestCase
{
    private const AT = '2026-10-07T10:11:12+00:00';

    public function testVolatileFieldsBecomeFunctionsOfTheDocumentAlone(): void
    {
        $first = PdfDeterminism::normalize($this->pdf('20261007013000', 'a', 'b'), new \DateTimeImmutable(self::AT));
        $second = PdfDeterminism::normalize($this->pdf('20271224235959', 'c', 'd'), new \DateTimeImmutable(self::AT));

        self::assertSame($first, $second);
        self::assertStringContainsString('/CreationDate (D:20261007101112)', $first);
        self::assertStringContainsString('/ModDate (D:20261007101112)', $first);
    }

    public function testEveryFieldKeepsItsLengthSoNoCrossReferenceOffsetMoves(): void
    {
        $original = $this->pdf('20261007013000', 'a', 'b');

        $normalized = PdfDeterminism::normalize($original, new \DateTimeImmutable(self::AT));

        self::assertSame(\strlen($original), \strlen($normalized));
        self::assertSame(strpos($original, 'xref'), strpos($normalized, 'xref'));
    }

    public function testADifferentBodyStillGetsADifferentId(): void
    {
        $one = PdfDeterminism::normalize($this->pdf('20261007013000', 'a', 'b', 'first body'), new \DateTimeImmutable(self::AT));
        $other = PdfDeterminism::normalize($this->pdf('20261007013000', 'a', 'b', 'other body'), new \DateTimeImmutable(self::AT));

        self::assertNotSame($one, $other);
        self::assertNotSame($this->idOf($one), $this->idOf($other));
    }

    public function testItIsIdempotent(): void
    {
        $once = PdfDeterminism::normalize($this->pdf('20261007013000', 'a', 'b'), new \DateTimeImmutable(self::AT));

        self::assertSame($once, PdfDeterminism::normalize($once, new \DateTimeImmutable(self::AT)));
    }

    public function testATimezoneSuffixIsKeptAndTheDateIsInUtc(): void
    {
        $pdf = "%PDF-1.4\n/CreationDate (D:20261007013000+01'00')\n/ModDate (D:20261007013000+01'00')\n";

        $normalized = PdfDeterminism::normalize($pdf, new \DateTimeImmutable('2026-10-07T23:30:00-02:00'));

        self::assertStringContainsString("/CreationDate (D:20261008013000+01'00')", $normalized);
    }

    public function testAPdfWithoutTheseFieldsPassesThroughUntouched(): void
    {
        self::assertSame("%PDF-1.4\n1 0 obj\n<< >>\nendobj\n", PdfDeterminism::normalize("%PDF-1.4\n1 0 obj\n<< >>\nendobj\n", new \DateTimeImmutable(self::AT)));
    }

    private function pdf(string $date, string $idA, string $idB, string $body = 'body'): string
    {
        $a = md5($idA);
        $b = md5($idB);

        return "%PDF-1.4\n1 0 obj\n<<\n/Producer (x)\n/CreationDate (D:{$date})\n/ModDate (D:{$date})\n>>\nendobj\n{$body}\nxref\n0 1\n0000000000 65535 f \ntrailer\n<<\n/Size 1\n/ID [<{$a}> <{$b}>]\n>>\nstartxref\n10\n%%EOF";
    }

    private function idOf(string $pdf): string
    {
        preg_match('/\/ID \[<([0-9a-f]{32})>/', $pdf, $m);

        return $m[1] ?? '';
    }
}
