<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain\Signing;

use App\Fiscal\Domain\Signing\PrintedHashMention;
use PHPUnit\Framework\TestCase;

/**
 * Despacho 8632/2014 §2.2.2's own verbatim example.
 */
final class PrintedHashMentionTest extends TestCase
{
    public function testMatchesTheDespachoExample(): void
    {
        // A hash whose 1.ª, 11.ª, 21.ª and 31.ª characters are exactly
        // A, x, A, x — reproducing the Despacho's own "AxAx-..." example.
        $hash = 'A'.str_repeat('z', 9).'x'.str_repeat('z', 9).'A'.str_repeat('z', 9).'x';

        self::assertSame(
            'AxAx-Processado por programa certificado n.º 0000/AT',
            PrintedHashMention::build($hash, '0000'),
        );
    }

    public function testTakesTheFirstEleventhTwentyFirstAndThirtyFirstCharacters(): void
    {
        $hash = 'mYJEv4iGwLcnQbRD7dPs2uD1mX08XjXIKcGg3GEHmwMhmmGYusfflJjTdSITLX+uujTwzqmL/U5nvt6S9s8ijN3LwkJXsiEpt099e1MET/8y3+Y1bN+K+YPJQiVmlQS0fXETsOPo8SwUZdBALt0vTo1VhUZKejACcjEYJG6nl=';

        self::assertSame('mc2X', PrintedHashMention::fourCharacters($hash));
        self::assertSame(
            'mc2X-Processado por programa certificado n.º 9999/AT',
            PrintedHashMention::build($hash, '9999'),
        );
    }
}
