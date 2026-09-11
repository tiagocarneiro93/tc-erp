import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostAuthRequestPasswordReset } from '@/api/generated'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const schema = z.object({
  email: z.string().min(1, 'Introduza o seu email').email('Email inválido'),
})

type FormValues = z.infer<typeof schema>

export function RequestPasswordResetForm() {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const requestReset = usePostAuthRequestPasswordReset()

  const onSubmit = handleSubmit((values) => {
    requestReset.mutate({ data: values })
  })

  if (requestReset.isSuccess) {
    return (
      <p>Se existir uma conta com esse email, foi enviada uma mensagem com instruções para repor a palavra-passe.</p>
    )
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="email">Email</Label>
        <Input id="email" type="email" autoComplete="username" {...register('email')} />
        {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
      </div>

      <Button type="submit" disabled={requestReset.isPending}>
        {requestReset.isPending ? 'A enviar…' : 'Enviar instruções'}
      </Button>
    </form>
  )
}
