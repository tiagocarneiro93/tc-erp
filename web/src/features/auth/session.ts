import { type GetMe200, getGetMeQueryOptions, useGetMe } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import type { QueryClient } from '@tanstack/react-query'

/**
 * `GET /me` doubles as the session check: a 401 means "not logged in",
 * which is an ordinary, expected outcome here — never retried, never
 * logged as an error.
 */
export function useSession() {
  const query = useGetMe<GetMe200, ApiError>({ query: { retry: false } })

  return {
    ...query,
    isAuthenticated: query.isSuccess,
    isUnauthenticated: query.isError && 401 === query.error.status,
  }
}

/**
 * Same query, usable from a router `beforeLoad` (outside React) to guard a route.
 */
export async function ensureSession(queryClient: QueryClient) {
  return queryClient.ensureQueryData({ ...getGetMeQueryOptions(), retry: false })
}
