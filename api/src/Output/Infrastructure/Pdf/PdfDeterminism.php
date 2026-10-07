<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Pdf;

use App\Output\Domain\PdfRenderingFailed;

/**
 * Engines stamp a PDF with the current time (`/CreationDate`, `/ModDate`) and a
 * random-looking file identifier (`/ID`), so the same document rendered a
 * second apart is a different file. That breaks "a re-download is identical"
 * and the exact-bytes premise of sealing. This rewrites those three values in
 * place, **keeping every field exactly as long as it was**, so no byte offset
 * in the cross-reference table moves and the file stays valid:
 *
 * - both dates become the document's own moment of issue;
 * - `/ID` becomes the MD5 of the rest of the file (itself already fixed).
 *
 * A PDF with no such fields (a different engine's) passes through untouched.
 */
final class PdfDeterminism
{
    private function __construct()
    {
    }

    public static function normalize(string $pdf, \DateTimeImmutable $timestamp): string
    {
        $date = $timestamp->setTimezone(new \DateTimeZone('UTC'))->format('YmdHis');

        $normalized = preg_replace_callback(
            '/\/(CreationDate|ModDate) \(D:(\d{14})([^)]*)\)/',
            static fn (array $m): string => \sprintf('/%s (D:%s%s)', $m[1], $date, preg_replace('/[^Z+\-\x27\d]/', '', $m[3])),
            $pdf,
        );

        if (null === $normalized) {
            throw new PdfRenderingFailed('The PDF dates could not be normalized.');
        }

        // The trailer's /ID [<…> <…>]: two 32-hex-digit strings (16 bytes each), separated by optional whitespace.
        $pattern = '/\/ID \[<([0-9a-fA-F]{32})>(\s*)<([0-9a-fA-F]{32})>\]/';
        $blank = str_repeat('0', 32);

        $withoutId = preg_replace_callback($pattern, static fn (array $m): string => \sprintf('/ID [<%s>%s<%s>]', $blank, $m[2], $blank), $normalized, 1, $count);

        if (null === $withoutId) {
            throw new PdfRenderingFailed('The PDF identifier could not be normalized.');
        }

        if (0 === $count) {
            return $normalized;
        }

        $id = md5($withoutId);

        return (string) preg_replace_callback($pattern, static fn (array $m): string => \sprintf('/ID [<%s>%s<%s>]', $id, $m[2], $id), $withoutId, 1);
    }
}
