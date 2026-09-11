import { useQueryClient } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'

import { getGetMeQueryOptions } from '@/api/generated'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ChangePasswordForm } from '@/features/auth/ChangePasswordForm'

export const Route = createFileRoute('/_authenticated/change-password')({
  component: ChangePasswordPage,
})

function ChangePasswordPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>Alterar palavra-passe</CardTitle>
          <CardDescription>É necessário definir uma nova palavra-passe para continuar.</CardDescription>
        </CardHeader>
        <CardContent>
          <ChangePasswordForm
            onSuccess={async () => {
              await queryClient.invalidateQueries({ queryKey: getGetMeQueryOptions().queryKey })
              await navigate({ to: '/companies' })
            }}
          />
        </CardContent>
      </Card>
    </div>
  )
}
