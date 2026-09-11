import { createFileRoute, useNavigate } from '@tanstack/react-router'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { LoginForm } from '@/features/auth/LoginForm'

export const Route = createFileRoute('/login')({
  component: LoginPage,
})

function LoginPage() {
  const navigate = useNavigate()

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>Entrar no tc-erp</CardTitle>
          <CardDescription>Introduza as suas credenciais para continuar.</CardDescription>
        </CardHeader>
        <CardContent>
          <LoginForm
            onSuccess={(mustChangePassword) => {
              void navigate({ to: mustChangePassword ? '/change-password' : '/companies' })
            }}
          />
        </CardContent>
      </Card>
    </div>
  )
}
