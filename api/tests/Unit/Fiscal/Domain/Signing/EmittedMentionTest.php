<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain\Signing;

use App\Fiscal\Domain\Signing\EmittedMention;
use PHPUnit\Framework\TestCase;

/**
 * Despacho 8632/2014 §2.2.3's own verbatim example.
 */
final class EmittedMentionTest extends TestCase
{
    public function testMatchesTheDespachoExample(): void
    {
        self::assertSame('Emitido por programa certificado n.º 0000/AT', EmittedMention::build('0000'));
    }
}
