<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Pdf;

use App\Output\Domain\PdfRenderingFailed;
use App\Output\Infrastructure\Pdf\GotenbergPdfEngine;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GotenbergPdfEngineTest extends TestCase
{
    private const PDF = "%PDF-1.4\n/CreationDate (D:20200101000000+00'00')\n/ModDate (D:20200101000000+00'00')\ntrailer\n<<\n/ID [<aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa><bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb>]\n>>\n%%EOF";

    public function testItPostsTheHtmlToChromiumAndNormalizesWhatComesBack(): void
    {
        $method = $url = $body = '';
        $client = new MockHttpClient(static function (string $requestMethod, string $requestUrl, array $options) use (&$method, &$url, &$body): MockResponse {
            $method = $requestMethod;
            $url = $requestUrl;
            $requestBody = $options['body'] ?? '';
            $body = \is_string($requestBody) ? $requestBody : '';

            return new MockResponse(self::PDF, ['http_code' => 200]);
        });

        $pdf = (new GotenbergPdfEngine($client, 'http://gotenberg:3000/'))->render('<html><body>Olá</body></html>', 'Fatura', new \DateTimeImmutable('2026-10-07T10:11:12+00:00'));

        self::assertSame('POST', $method);
        self::assertSame('http://gotenberg:3000/forms/chromium/convert/html', $url);
        self::assertStringContainsString('name="files"; filename="index.html"', $body);
        self::assertStringContainsString('<html><body>Olá</body></html>', $body);
        self::assertStringContainsString('name="paperWidth"', $body);
        self::assertStringContainsString('/CreationDate (D:20261007101112+00\'00\')', $pdf, 'Same determinism step as every engine.');
    }

    public function testAnErrorStatusIsARenderingFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('boom', ['http_code' => 503]));

        $this->expectException(PdfRenderingFailed::class);

        (new GotenbergPdfEngine($client, 'http://gotenberg:3000'))->render('<html/>', 'x', new \DateTimeImmutable());
    }

    public function testAnUnreachableServiceIsARenderingFailure(): void
    {
        $client = new MockHttpClient(static fn () => throw new TransportException('connection refused'));

        $this->expectException(PdfRenderingFailed::class);
        $this->expectExceptionMessage('connection refused');

        (new GotenbergPdfEngine($client, 'http://gotenberg:3000'))->render('<html/>', 'x', new \DateTimeImmutable());
    }
}
