<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Pdf;

use App\Output\Domain\PdfEngine;
use App\Output\Domain\PdfRenderingFailed;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The other candidate of docs/plans/phase-3.md decision 7: the same HTML sent
 * to a Gotenberg service (headless Chromium) — full modern CSS and the best
 * layout fidelity, at the price of one more container to run, monitor and
 * keep patched. Not wired by default: swapping it in is the one alias in
 * `config/services.yaml`. Kept in the codebase until the owner picks an
 * engine; delete whichever loses.
 *
 * Same determinism step as mPDF ({@see PdfDeterminism}) — Chromium stamps the
 * current time and a document id as well.
 */
final class GotenbergPdfEngine implements PdfEngine
{
    private const A4_WIDTH_INCHES = 8.27;
    private const A4_HEIGHT_INCHES = 11.7;
    private const MARGIN_INCHES = 0.47;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function render(string $html, string $title, \DateTimeImmutable $timestamp): string
    {
        $boundary = 'tcerp'.bin2hex(random_bytes(8));
        $fields = [
            'paperWidth' => (string) self::A4_WIDTH_INCHES,
            'paperHeight' => (string) self::A4_HEIGHT_INCHES,
            'marginTop' => (string) self::MARGIN_INCHES,
            'marginBottom' => (string) self::MARGIN_INCHES,
            'marginLeft' => (string) self::MARGIN_INCHES,
            'marginRight' => (string) self::MARGIN_INCHES,
            'printBackground' => 'true',
        ];

        $body = '';

        foreach ($fields as $name => $value) {
            $body .= \sprintf("--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n", $boundary, $name, $value);
        }

        $body .= \sprintf("--%s\r\nContent-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\nContent-Type: text/html\r\n\r\n%s\r\n--%s--\r\n", $boundary, $html, $boundary);

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/forms/chromium/convert/html', [
                'headers' => ['Content-Type' => 'multipart/form-data; boundary='.$boundary],
                'body' => $body,
                'timeout' => 60,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new PdfRenderingFailed(\sprintf('Gotenberg answered HTTP %d.', $response->getStatusCode()));
            }

            $pdf = $response->getContent();
        } catch (PdfRenderingFailed $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PdfRenderingFailed('Gotenberg could not render the document: '.$e->getMessage(), 0, $e);
        }

        return PdfDeterminism::normalize($pdf, $timestamp);
    }
}
