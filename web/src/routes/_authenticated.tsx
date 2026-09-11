import { getGetMeQueryOptions } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { createFileRoute, redirect } from '@tanstack/react-router'

/**
 * Every protected route is a child of this pathless layout. Mirrors the
 * API's own `MustChangePasswordListener` (CLAUDE.md task 0.8): a session
 * with `must_change_password` still true is redirected to
 * `/change-password` no matter which protected route it tried to reach.
 */
export const Route = createFileRoute('/_authenticated')({
  beforeLoad: async ({ context, location }) => {
    try {
      const me = await context.queryClient.ensureQueryData({ ...getGetMeQueryOptions(), retry: false })

      if (me.user?.must_change_password && '/change-password' !== location.pathname) {
        throw redirect({ to: '/change-password' })
      }

      return { me }
    } catch (error) {
      if (error instanceof ApiError && 401 === error.status) {
        throw redirect({ to: '/login' })
      }

      throw error
    }
  },
})
