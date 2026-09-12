import { useState } from 'react'

/**
 * Client-side state for the API's cursor pagination (technical-scope.md
 * §9: "Cursor pagination for lists" — `{ items, next_cursor }` responses,
 * `{ search, cursor, limit }` params). Keeps the visited cursors on a
 * stack so "Anterior" can step back without a second request type;
 * changing the search term resets to the first page.
 */
export function useCursorPagination() {
  const [search, setSearchValue] = useState('')
  const [cursorStack, setCursorStack] = useState<string[]>([])

  return {
    search,
    setSearch: (value: string) => {
      setSearchValue(value)
      setCursorStack([])
    },
    cursor: cursorStack.at(-1),
    hasPrev: cursorStack.length > 0,
    goNext: (nextCursor: string) => setCursorStack((stack) => [...stack, nextCursor]),
    goPrev: () => setCursorStack((stack) => stack.slice(0, -1)),
  }
}
