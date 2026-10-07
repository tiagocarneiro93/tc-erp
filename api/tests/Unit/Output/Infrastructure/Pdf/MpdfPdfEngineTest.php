<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Pdf;

use App\Output\Infrastructure\Pdf\MpdfPdfEngine;
use PHPUnit\Framework\TestCase;

final class MpdfPdfEngineTest extends TestCase
{
    private const HTML = '<html><body><h1>Fatura FT 2026A/1</h1><p>Total 1&nbsp;234,56&nbsp;€ — ação, coração, Évora.</p></body></html>';

    public function testItProducesAPdf(): void
    {
        $pdf = $this->engine()->render(self::HTML, 'Fatura', new \DateTimeImmutable('2026-10-07T10:11:12+00:00'));

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringEndsWith('%%EOF', rtrim($pdf));
        self::assertStringContainsString('/Title', $pdf);
    }

    public function testTheSameInputIsByteIdenticalEvenSecondsApart(): void
    {
        $engine = $this->engine();
        $at = new \DateTimeImmutable('2026-10-07T10:11:12+00:00');

        $first = $engine->render(self::HTML, 'Fatura', $at);
        sleep(2);
        $second = $engine->render(self::HTML, 'Fatura', $at);

        self::assertSame($first, $second, 'No timestamp or random identifier may leak into the file.');
    }

    public function testDifferentContentGivesADifferentFile(): void
    {
        $engine = $this->engine();
        $at = new \DateTimeImmutable('2026-10-07T10:11:12+00:00');

        self::assertNotSame(
            $engine->render(self::HTML, 'Fatura', $at),
            $engine->render(str_replace('1&nbsp;234,56', '9&nbsp;999,99', self::HTML), 'Fatura', $at),
        );
    }

    private function engine(): MpdfPdfEngine
    {
        return new MpdfPdfEngine(sys_get_temp_dir().'/tcerp-mpdf-test');
    }
}
