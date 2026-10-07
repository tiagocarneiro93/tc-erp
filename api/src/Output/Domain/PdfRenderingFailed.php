<?php

declare(strict_types=1);

namespace App\Output\Domain;

/**
 * The engine could not produce a PDF — a plain exception → generic 500 and
 * Sentry (nothing the caller can fix), never a half-rendered fiscal document.
 */
final class PdfRenderingFailed extends \RuntimeException
{
}
