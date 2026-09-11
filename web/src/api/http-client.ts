/**
 * The one place allowed to call `fetch` directly (technical-scope.md §11:
 * "Only the generated API client and TanStack Query hooks talk to the
 * API; no hand-written fetch") — orval calls this for every generated
 * request. Cookie session auth (CLAUDE.md task 0.8) needs `credentials:
 * 'include'`; the CSRF header pairs with `SameSite=Lax`
 * (`CsrfHeaderListener`) for every state-changing request.
 */

export class ApiError extends Error {
  readonly status: number
  readonly problem: unknown

  constructor(status: number, problem: unknown) {
    super(`API request failed with status ${status}`)
    this.status = status
    this.problem = problem
  }
}

const UNSAFE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

export async function httpClient<T>(url: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? 'GET').toUpperCase()
  const headers = new Headers(init.headers)

  if (UNSAFE_METHODS.has(method)) {
    headers.set('X-Requested-With', 'XMLHttpRequest')
  }

  if (init.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(url, {
    ...init,
    method,
    headers,
    credentials: 'include',
  })

  if (!response.ok) {
    const contentType = response.headers.get('Content-Type') ?? ''
    const problem: unknown = contentType.includes('json') ? await response.json() : await response.text()
    throw new ApiError(response.status, problem)
  }

  if (204 === response.status || 202 === response.status) {
    return undefined as T
  }

  return (await response.json()) as T
}

export default httpClient
