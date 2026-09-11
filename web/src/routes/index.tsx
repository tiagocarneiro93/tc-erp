import { createFileRoute, redirect } from '@tanstack/react-router'

import { getGetMeQueryOptions } from '@/api/generated'
import { ApiError } from '@/api/http-client'

export const Route = createFileRoute('/')({
  beforeLoad: async ({ context }) => {
    try {
      await context.queryClient.ensureQueryData({ ...getGetMeQueryOptions(), retry: false })
      throw redirect({ to: '/companies' })
    } catch (error) {
      if (error instanceof ApiError && 401 === error.status) {
        throw redirect({ to: '/login' })
      }

      throw error
    }
  },
})
