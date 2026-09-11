import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostAuthChangePassword } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { PasswordRequirements } from '@/components/auth/PasswordRequirements'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PASSWORD_POLICY_ERROR_MESSAGE, PASSWORD_POLICY_REGEX } from '@/lib/password-policy'

const schema = z.object({
  current_password: z.string().min(1, 'Introduza a palavra-passe atual'),
  new_password: z.string().regex(PASSWORD_POLICY_REGEX, PASSWORD_POLICY_ERROR_MESSAGE),
})

type FormValues = z.infer<typeof schema>

interface ChangePasswordFormProps {
  onSuccess: () => void
}

export function ChangePasswordForm({ onSuccess }: ChangePasswordFormProps) {
  const {
    register,
    handleSubmit,
    watch,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { current_password: '', new_password: '' } })

  const changePassword = usePostAuthChangePassword<ApiError>()

  const onSubmit = handleSubmit((values) => {
    changePassword.mutate({ data: values }, { onSuccess })
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="current_password">Palavra-passe atual</Label>
        <Input
          id="current_password"
          type="password"
          autoComplete="current-password"
          {...register('current_password')}
        />
        {errors.current_password && <p className="text-destructive text-sm">{errors.current_password.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="new_password">Nova palavra-passe</Label>
        <Input id="new_password" type="password" autoComplete="new-password" {...register('new_password')} />
        <PasswordRequirements password={watch('new_password')} />
      </div>

      {changePassword.isError && (
        <p className="text-destructive text-sm" role="alert">
          {422 === changePassword.error.status
            ? 'A palavra-passe atual está incorreta.'
            : 'Ocorreu um erro. Tente novamente.'}
        </p>
      )}

      <Button type="submit" disabled={changePassword.isPending}>
        {changePassword.isPending ? 'A alterar…' : 'Alterar palavra-passe'}
      </Button>
    </form>
  )
}
