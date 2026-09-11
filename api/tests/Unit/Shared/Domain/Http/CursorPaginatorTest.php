<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Http;

use App\Shared\Domain\Http\CursorPaginator;
use PHPUnit\Framework\TestCase;

final class CursorPaginatorTest extends TestCase
{
    private CursorPaginator $paginator;

    protected function setUp(): void
    {
        $this->paginator = new CursorPaginator();
    }

    public function testReturnsEverythingWhenUnderTheLimit(): void
    {
        $page = $this->paginator->paginate(['a', 'b', 'c'], null, 50, static fn (string $s): string => $s);

        self::assertSame(['a', 'b', 'c'], $page->items);
        self::assertNull($page->nextCursor);
    }

    public function testCutsAtTheLimitAndReturnsANextCursor(): void
    {
        $page = $this->paginator->paginate(['a', 'b', 'c'], null, 2, static fn (string $s): string => $s);

        self::assertSame(['a', 'b'], $page->items);
        self::assertSame('b', $page->nextCursor);
    }

    public function testResumesAfterTheGivenCursor(): void
    {
        $page = $this->paginator->paginate(['a', 'b', 'c'], 'b', 50, static fn (string $s): string => $s);

        self::assertSame(['c'], $page->items);
        self::assertNull($page->nextCursor);
    }

    public function testAnUnknownCursorYieldsAnEmptyPageRatherThanTheFirstPage(): void
    {
        $page = $this->paginator->paginate(['a', 'b', 'c'], 'not-a-real-cursor', 50, static fn (string $s): string => $s);

        self::assertSame([], $page->items);
        self::assertNull($page->nextCursor);
    }

    public function testANonPositiveLimitFallsBackToTheDefault(): void
    {
        $items = array_map(static fn (int $i): string => (string) $i, range(1, 60));

        $page = $this->paginator->paginate($items, null, 0, static fn (string $s): string => $s);

        self::assertCount(50, $page->items);
    }

    public function testTheLimitIsCappedAtTheMaximum(): void
    {
        $items = array_map(static fn (int $i): string => (string) $i, range(1, 300));

        $page = $this->paginator->paginate($items, null, 1000, static fn (string $s): string => $s);

        self::assertCount(200, $page->items);
    }
}
