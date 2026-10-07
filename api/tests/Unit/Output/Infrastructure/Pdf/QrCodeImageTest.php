<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Infrastructure\Pdf;

use App\Output\Infrastructure\Pdf\QrCodeImage;
use chillerlan\QRCode\Common\EccLevel;
use PHPUnit\Framework\TestCase;

/**
 * `docs/legal/at-qrcode-spec.pdf` §2: ECC M, version 9 at least.
 */
final class QrCodeImageTest extends TestCase
{
    private const SAMPLE_PAYLOAD = 'A:508025090*B:123456789*C:PT*D:FT*E:N*F:20261007*G:FT 2026A/1*H:CSDF7T5H-1*I1:PT*I7:100.00*I8:23.00*N:23.00*O:123.00*Q:AbCd*R:0000';

    public function testItUsesErrorCorrectionMAndVersionNineAtLeast(): void
    {
        $matrix = (new QrCodeImage())->matrix(self::SAMPLE_PAYLOAD);

        self::assertSame(EccLevel::M, $matrix->getEccLevel()?->getLevel());
        self::assertGreaterThanOrEqual(9, $matrix->getVersion()?->getVersionNumber());
    }

    public function testAShortPayloadIsStillAtLeastVersionNine(): void
    {
        $matrix = (new QrCodeImage())->matrix('A:508025090*B:999999990');

        self::assertSame(9, $matrix->getVersion()?->getVersionNumber(), 'The spec\'s v=9 is a minimum, even for a short message.');
    }

    public function testItIsAnSvgDataUri(): void
    {
        $uri = (new QrCodeImage())->dataUri(self::SAMPLE_PAYLOAD);

        self::assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        self::assertStringContainsString('<svg', (string) base64_decode(substr($uri, \strlen('data:image/svg+xml;base64,')), true));
    }

    public function testTheSamePayloadAlwaysGivesTheSameImage(): void
    {
        $qr = new QrCodeImage();

        self::assertSame($qr->dataUri(self::SAMPLE_PAYLOAD), $qr->dataUri(self::SAMPLE_PAYLOAD));
    }
}
