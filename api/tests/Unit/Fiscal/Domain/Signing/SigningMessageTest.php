<?php

declare(strict_types=1);

namespace App\Tests\Unit\Fiscal\Domain\Signing;

use App\Fiscal\Domain\Signing\SigningMessage;
use App\Shared\Domain\Decimal\Money;
use PHPUnit\Framework\TestCase;

/**
 * Despacho 8632/2014 §4.5's own worked example (§4.6) and §7.1's first-
 * registo example (empty previous hash) — both verbatim, not invented.
 */
final class SigningMessageTest extends TestCase
{
    public function testMatchesTheDespachoWorkedExample(): void
    {
        $message = SigningMessage::build(
            new \DateTimeImmutable('2013-07-01'),
            new \DateTimeImmutable('2013-07-01T11:27:08'),
            'FS 001/0009',
            Money::fromString('200.00'),
            'mYJEv4iGwLcnQbRD7dPs2uD1mX08XjXIKcGg3GEHmwMhmmGYusfflJjTdSITLX+uujTwzqmL/U5nvt6S9s8ijN3LwkJXsiEpt099e1MET/8y3+Y1bN+K+YPJQiVmlQS0fXETsOPo8SwUZdBALt0vTo1VhUZKejACcjEYJG6nl=',
        );

        self::assertSame(
            '2013-07-01;2013-07-01T11:27:08;FS 001/0009;200.00;mYJEv4iGwLcnQbRD7dPs2uD1mX08XjXIKcGg3GEHmwMhmmGYusfflJjTdSITLX+uujTwzqmL/U5nvt6S9s8ijN3LwkJXsiEpt099e1MET/8y3+Y1bN+K+YPJQiVmlQS0fXETsOPo8SwUZdBALt0vTo1VhUZKejACcjEYJG6nl=',
            $message,
        );
    }

    public function testTheFirstDocumentInASeriesSignsWithAnEmptyPreviousHash(): void
    {
        $message = SigningMessage::build(
            new \DateTimeImmutable('2010-05-18'),
            new \DateTimeImmutable('2010-05-18T11:22:19'),
            'FAC 001/14',
            Money::fromString('3.12'),
            null,
        );

        self::assertSame('2010-05-18;2010-05-18T11:22:19;FAC 001/14;3.12;', $message);
    }
}
