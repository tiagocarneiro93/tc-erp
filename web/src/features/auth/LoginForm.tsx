import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { getGetMeQueryOptions, usePostAuthLogin } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const schema = z.object({
  email: z.string().min(1, 'Introduza o seu email').email('Email inválido'),
  password: z.string().min(1, 'Introduza a sua palavra-passe'),
})

type FormValues = z.infer<typeof schema>

interface LoginFormProps {
  onSuccess: (mustChangePassword: boolean) => void
}

export function LoginForm({ onSuccess }: LoginFormProps) {
  const queryClient = useQueryClient()
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const login = usePostAuthLogin<ApiError>()

  const onSubmit = handleSubmit((values) => {
    login.mutate(
      { data: values },
      {
        onSuccess: async (result) => {
          await queryClient.invalidateQueries({ queryKey: getGetMeQueryOptions().queryKey })
          onSuccess(result.must_change_password ?? false)
        },
      },
    )
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="email">Email</Label>
        <Input id="email" type="email" autoComplete="username" {...register('email')} />
        {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="password">Palavra-passe</Label>
        <Input id="password" type="password" autoComplete="current-password" {...register('password')} />
        {errors.password && <p className="text-destructive text-sm">{errors.password.message}</p>}
      </div>

      {login.isError && (
        <p className="text-destructive text-sm" role="alert">
          {401 === login.error.status ? 'Email ou palavra-passe incorretos.' : 'Ocorreu um erro. Tente novamente.'}
        </p>
      )}

      <Button type="submit" disabled={login.isPending}>
        {login.isPending ? 'A entrar…' : 'Entrar'}
      </Button>
    </form>
  )
}
