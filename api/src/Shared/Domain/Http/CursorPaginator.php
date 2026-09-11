<?php

declare(strict_types=1);

namespace App\Shared\Domain\Http;

/**
 * Cursor pagination over an already-fetched, ascending-ordered list
 * (technical-scope.md §9.1: `?cursor=…&limit=50`). `cursor` is opaque to
 * the client — here it is simply the last-seen item's own id — and the
 * response never exposes a total count or page number, only whether
 * `next_cursor` is present.
 *
 * Lives in Shared.Domain, not Infrastructure: it touches no framework
 * class, and UI/Http controllers across every module need to depend on it
 * (Deptrac forbids UI/Http depending on another tier's Infrastructure).
 *
 * Paginating an in-memory list is a Phase 0 simplification: nothing yet
 * needs page 2 of a list large enough to matter (companies a user belongs
 * to). A large list (documents, stock movements) will need this same
 * contract applied to a `WHERE id > :cursor ORDER BY id LIMIT :limit`
 * query instead — the point of a shared helper is that call sites change,
 * this class's behaviour does not.
 */
final class CursorPaginator
{
    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;

    /**
     * @template T
     *
     * @param list<T>             $items    ordered ascending by whatever `$cursorOf` reads
     * @param \Closure(T): string $cursorOf
     *
     * @return CursorPage<T>
     */
    public function paginate(array $items, ?string $cursor, ?int $limit, \Closure $cursorOf): CursorPage
    {
        $limit = $this->normalizeLimit($limit);
        $items = $this->afterCursor($items, $cursor, $cursorOf);

        $page = \array_slice($items, 0, $limit);
        $hasMore = \count($items) > $limit;
        $lastItem = $page[array_key_last($page)] ?? null;

        return new CursorPage(
            $page,
            $hasMore && null !== $lastItem ? $cursorOf($lastItem) : null,
        );
    }

    /**
     * @template T
     *
     * @param list<T>             $items
     * @param \Closure(T): string $cursorOf
     *
     * @return list<T>
     */
    private function afterCursor(array $items, ?string $cursor, \Closure $cursorOf): array
    {
        if (null === $cursor) {
            return $items;
        }

        foreach ($items as $index => $item) {
            if ($cursorOf($item) === $cursor) {
                return \array_slice($items, $index + 1);
            }
        }

        // An unknown cursor (stale, tampered with) yields an empty page
        // rather than silently falling back to the first page.
        return [];
    }

    private function normalizeLimit(?int $limit): int
    {
        if (null === $limit || $limit <= 0) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }
}
