<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Pdf;

use App\Output\Domain\DocumentPdfRenderer;
use App\Output\Domain\PdfEngine;
use App\Output\Domain\PdfRenderingFailed;
use App\Shared\Domain\Fiscal\PrintableDocument;
use App\Shared\Domain\Fiscal\PrintableParty;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * technical-scope.md §7.8: a deterministic rendering of stored data. The
 * template is chosen by the version the document was issued with
 * (`templates/pdf/<version>/document.html.twig`); old versions stay in the
 * codebase so a document re-rendered years later looks as it did.
 *
 * Twig runs with `strict_variables` (a typo in a template is an error, never a
 * silently empty field on a fiscal document), no cache of its own and
 * autoescape on.
 */
final class TwigDocumentPdfRenderer implements DocumentPdfRenderer
{
    private readonly Environment $twig;

    public function __construct(
        string $templateDirectory,
        private readonly PdfEngine $engine,
        QrCodeImage $qrCode,
        private readonly PrintableParty $softwareProducer,
    ) {
        $this->twig = new Environment(new FilesystemLoader($templateDirectory), [
            'strict_variables' => true,
            'autoescape' => 'html',
            'cache' => false,
        ]);
        $this->twig->addExtension(new PdfTwigExtension($qrCode));
    }

    public function render(PrintableDocument $document, string $copyLabel): string
    {
        $template = \sprintf('%s/document.html.twig', $document->templateVersion);

        if (1 !== preg_match('/^v\d+$/', $document->templateVersion) || !$this->twig->getLoader()->exists($template)) {
            throw new PdfRenderingFailed(\sprintf('Document %s was issued with PDF template "%s", which this codebase no longer has.', $document->documentNo, $document->templateVersion));
        }

        $html = $this->twig->render($template, [
            'document' => $document,
            'copy_label' => $copyLabel,
            'software_producer' => $this->softwareProducer,
        ]);

        return $this->engine->render($html, \sprintf('%s %s', $document->documentTypeName, $document->documentNo), $document->systemEntryAt);
    }
}
