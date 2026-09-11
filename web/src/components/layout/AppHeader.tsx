import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'

import { getGetMeQueryOptions, usePostAuthLogout } from '@/api/generated'
import { Button } from '@/components/ui/button'
import { CompanySwitcher } from '@/features/companies/CompanySwitcher'

export function AppHeader() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const logout = usePostAuthLogout()

  return (
    <header className="flex items-center justify-between border-b p-4">
      <CompanySwitcher />
      <Button
        variant="ghost"
        onClick={async () => {
          // The session is invalidated server-side before the redirect
          // logout responds with (security.yaml); a client-side "error"
          // following that redirect doesn't mean logout failed.
          try {
            await logout.mutateAsync()
          } catch {
            // ignored — see above
          }

          queryClient.removeQueries({ queryKey: getGetMeQueryOptions().queryKey })
          await navigate({ to: '/login' })
        }}
      >
        Sair
      </Button>
    </header>
  )
}
