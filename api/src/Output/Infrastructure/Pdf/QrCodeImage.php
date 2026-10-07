<?php

declare(strict_types=1);

namespace App\Output\Infrastructure\Pdf;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * The document's QR code as an SVG image (a data URI the template embeds),
 * built to `docs/legal/at-qrcode-spec.pdf` §2: error correction **M**, data
 * type **Byte**, version **9 at least** (a longer message may need a higher
 * one — the spec's 9 is a minimum), and the message is the document's stored
 * `qr_payload`, verbatim. The two physical requirements — at least 30×30 mm
 * and a 0.25 cm margin — are set by the template, which sizes the image.
 *
 * Vector output: sharp at any print resolution, no `ext-gd` needed for it.
 */
final class QrCodeImage
{
    public function dataUri(string $payload): string
    {
        $uri = $this->qrCode($payload)->render();

        if (!\is_string($uri)) {
            throw new \RuntimeException('The QR code could not be rendered.');
        }

        return $uri;
    }

    /**
     * The encoded matrix, for tests to confirm the spec's version and error
     * correction level on what is actually produced.
     */
    public function matrix(string $payload): QRMatrix
    {
        return $this->qrCode($payload)->getQRMatrix();
    }

    private function qrCode(string $payload): QRCode
    {
        $options = new QROptions([
            'versionMin' => 9,
            'versionMax' => 40,
            'eccLevel' => EccLevel::M,
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'quietzoneSize' => 0,
            'drawLightModules' => false,
            'svgUseFillAttributes' => true,
        ]);

        $qrCode = new QRCode($options);
        $qrCode->addByteSegment($payload);

        return $qrCode;
    }
}
