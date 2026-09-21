<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain\Signing;

use App\Fiscal\Domain\Signing\AtcudBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Portaria 195/2020 Art. 3.º/4.º and `at-qrcode-spec.pdf` field H's own
 * example (`CSDF7T5H-0035`).
 */
final class AtcudBuilderTest extends TestCase
{
    public function testBuildsTheStoredValue(): void
    {
        self::assertSame('CSDF7T5H-35', AtcudBuilder::build('CSDF7T5H', 35));
    }

    public function testPrintedFormAddsThePrefix(): void
    {
        self::assertSame('ATCUD:CSDF7T5H-35', AtcudBuilder::printed('CSDF7T5H-35'));
    }
}
