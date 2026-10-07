<?php

declare(strict_types=1);

namespace App\Tests\Unit\Output\Domain;

use App\Output\Domain\CopyLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CopyLabelTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function labels(): iterable
    {
        yield 'first hand-out' => [0, 'Original'];
        yield 'second' => [1, 'Duplicado'];
        yield 'third' => [2, 'Triplicado'];
        yield 'fourth' => [3, '4.ª via'];
        yield 'tenth' => [9, '10.ª via'];
        yield 'never negative' => [-3, 'Original'];
    }

    #[DataProvider('labels')]
    public function testOnlyTheFirstHandOutIsTheOriginal(int $previously, string $expected): void
    {
        self::assertSame($expected, CopyLabel::forNextCopy($previously));
    }
}
