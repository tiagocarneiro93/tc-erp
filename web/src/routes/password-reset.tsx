import { createFileRoute } from '@tanstack/react-router'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { RequestPasswordResetForm } from '@/features/auth/RequestPasswordResetForm'

export const Route = createFileRoute('/password-reset')({
  component: PasswordResetPage,
})

function PasswordResetPage() {
  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>Repor palavra-passe</CardTitle>
          <CardDescription>Enviamos-lhe um link para definir uma nova palavra-passe.</CardDescription>
        </CardHeader>
        <CardContent>
          <RequestPasswordResetForm />
        </CardContent>
      </Card>
    </div>
  )
}
