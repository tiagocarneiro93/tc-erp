import { Outlet, createFileRoute, redirect } from '@tanstack/react-router'

import { getGetMeQueryOptions } from '@/api/generated'
import { AppHeader } from '@/components/layout/AppHeader'
import { AppSidebar } from '@/components/layout/AppSidebar'

/**
 * `{companyId}` is only ever valid if the session's own `/me` companies
 * list includes it — the API's `CompanyRouteListener` enforces the same
 * membership check server-side (technical-scope.md §5.3); this is purely
 * so the UI doesn't render a company switcher pointed at a 404.
 */
export const Route = createFileRoute('/_authenticated/c/$companyId')({
  beforeLoad: async ({ context, params }) => {
    const me = await context.queryClient.ensureQueryData(getGetMeQueryOptions())
    const isMember = (me.companies ?? []).some((company) => company.id === params.companyId)

    if (!isMember) {
      throw redirect({ to: '/companies' })
    }
  },
  component: CompanyLayout,
})

function CompanyLayout() {
  return (
    <div className="flex min-h-svh flex-col">
      <AppHeader />
      <div className="flex flex-1">
        <AppSidebar companyId={Route.useParams().companyId} />
        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
