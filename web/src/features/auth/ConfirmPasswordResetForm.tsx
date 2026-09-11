import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostAuthConfirmPasswordReset } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { PasswordRequirements } from '@/components/auth/PasswordRequirements'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PASSWORD_POLICY_ERROR_MESSAGE, PASSWORD_POLICY_REGEX } from '@/lib/password-policy'

const schema = z.object({
  new_password: z.string().regex(PASSWORD_POLICY_REGEX, PASSWORD_POLICY_ERROR_MESSAGE),
})

type FormValues = z.infer<typeof schema>

interface ConfirmPasswordResetFormProps {
  token: string
  onSuccess: () => void
}

export function ConfirmPasswordResetForm({ token, onSuccess }: ConfirmPasswordResetFormProps) {
  const { register, handleSubmit, watch } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { new_password: '' },
  })

  const confirmReset = usePostAuthConfirmPasswordReset<ApiError>()

  const onSubmit = handleSubmit((values) => {
    confirmReset.mutate({ data: { token, ...values } }, { onSuccess })
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="new_password">Nova palavra-passe</Label>
        <Input id="new_password" type="password" autoComplete="new-password" {...register('new_password')} />
        <PasswordRequirements password={watch('new_password')} />
      </div>

      {confirmReset.isError && (
        <p className="text-destructive text-sm" role="alert">
          Este link é inválido ou já expirou.
        </p>
      )}

      <Button type="submit" disabled={confirmReset.isPending}>
        {confirmReset.isPending ? 'A definir…' : 'Definir palavra-passe'}
      </Button>
    </form>
  )
}
