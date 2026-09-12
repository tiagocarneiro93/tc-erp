import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostAtCredentialsTest, usePutAtCredentialsUpdate } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api-error'

const schema = z.object({
  subuser: z.string().min(1, 'Introduza o subutilizador'),
  password: z.string().min(1, 'Introduza a palavra-passe'),
})

type FormValues = z.infer<typeof schema>

export function AtCredentialsForm({ companyId }: { companyId: string }) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const update = usePutAtCredentialsUpdate<ApiError>()
  const test = usePostAtCredentialsTest<ApiError>()

  const onSubmit = handleSubmit((values) => {
    update.mutate({ companyId, data: values })
  })

  return (
    <div className="flex flex-col gap-4">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        <div className="grid grid-cols-2 gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="at-subuser">Subutilizador AT</Label>
            <Input id="at-subuser" {...register('subuser')} />
            {errors.subuser && <p className="text-destructive text-sm">{errors.subuser.message}</p>}
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="at-password">Palavra-passe</Label>
            <Input id="at-password" type="password" {...register('password')} />
            {errors.password && <p className="text-destructive text-sm">{errors.password.message}</p>}
          </div>
        </div>

        {update.isError && (
          <p className="text-destructive text-sm" role="alert">
            {apiErrorMessage(update.error, { 403: 'Sem permissão para gerir a empresa.' })}
          </p>
        )}

        {update.isSuccess && <p className="text-sm text-green-700">Credenciais guardadas.</p>}

        <Button type="submit" disabled={update.isPending} className="w-fit">
          {update.isPending ? 'A guardar…' : 'Guardar credenciais'}
        </Button>
      </form>

      <div className="flex flex-col gap-2 border-t pt-4">
        <Button
          type="button"
          variant="outline"
          className="w-fit"
          disabled={test.isPending}
          onClick={() => test.mutate({ companyId })}
        >
          {test.isPending ? 'A testar…' : 'Testar ligação'}
        </Button>

        {test.isSuccess && (
          <p className={test.data.valid ? 'text-sm text-green-700' : 'text-destructive text-sm'}>
            {test.data.valid ? 'Ligação válida.' : (test.data.error ?? 'Ligação inválida.')}
          </p>
        )}
      </div>
    </div>
  )
}
