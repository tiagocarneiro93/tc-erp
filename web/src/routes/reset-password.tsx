import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ConfirmPasswordResetForm } from '@/features/auth/ConfirmPasswordResetForm'

const searchSchema = z.object({
  token: z.string().catch(''),
})

export const Route = createFileRoute('/reset-password')({
  validateSearch: searchSchema,
  component: ResetPasswordPage,
})

function ResetPasswordPage() {
  const navigate = useNavigate()
  const { token } = Route.useSearch()

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>Definir palavra-passe</CardTitle>
          <CardDescription>Escolha uma nova palavra-passe para a sua conta.</CardDescription>
        </CardHeader>
        <CardContent>
          {token ? (
            <ConfirmPasswordResetForm token={token} onSuccess={() => void navigate({ to: '/login' })} />
          ) : (
            <p className="text-muted-foreground text-sm">Este link está incompleto.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
