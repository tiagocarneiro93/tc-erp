import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostPriceListsCreate } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api-error'

const schema = z.object({
  name: z.string().min(1, 'Introduza o nome'),
  default_includes_vat: z.boolean(),
})

type FormValues = z.infer<typeof schema>

export function PriceListForm({ companyId, onSuccess }: { companyId: string; onSuccess: () => void }) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { default_includes_vat: true } })

  const create = usePostPriceListsCreate<ApiError>()

  const onSubmit = handleSubmit((values) => {
    create.mutate({ companyId, data: values }, { onSuccess })
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="price-list-name">Nome</Label>
        <Input id="price-list-name" {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>

      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" className="border-input size-4 rounded" {...register('default_includes_vat')} />
        Os preços desta tabela incluem IVA, por omissão
      </label>

      {create.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(create.error, { 403: 'Sem permissão para gerir produtos.' })}
        </p>
      )}

      <Button type="submit" disabled={create.isPending}>
        {create.isPending ? 'A criar…' : 'Criar tabela de preços'}
      </Button>
    </form>
  )
}
