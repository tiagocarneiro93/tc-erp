import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostAuthConfirmPasswordReset } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const schema = z.object({
  new_password: z.string().min(1, 'Introduza a nova palavra-passe'),
})

type FormValues = z.infer<typeof schema>

interface ConfirmPasswordResetFormProps {
  token: string
  onSuccess: () => void
}

export function ConfirmPasswordResetForm({ token, onSuccess }: ConfirmPasswordResetFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const confirmReset = usePostAuthConfirmPasswordReset<ApiError>()

  const onSubmit = handleSubmit((values) => {
    confirmReset.mutate({ data: { token, ...values } }, { onSuccess })
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="new_password">Nova palavra-passe</Label>
        <Input id="new_password" type="password" autoComplete="new-password" {...register('new_password')} />
        {errors.new_password && <p className="text-destructive text-sm">{errors.new_password.message}</p>}
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
