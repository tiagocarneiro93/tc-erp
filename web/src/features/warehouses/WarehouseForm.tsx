import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostWarehousesCreate, usePutWarehousesUpdate, type GetWarehousesGet200 } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api-error'
import { blankToNull } from '@/lib/forms'

const schema = z.object({
  code: z.string().min(1, 'Introduza o código'),
  name: z.string().min(1, 'Introduza o nome'),
  address: z.string().optional(),
  is_default: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface WarehouseFormProps {
  companyId: string
  warehouse?: GetWarehousesGet200 & { id: string }
  onSuccess: () => void
}

export function WarehouseForm({ companyId, warehouse, onSuccess }: WarehouseFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: warehouse?.code ?? '',
      name: warehouse?.name ?? '',
      address: warehouse?.address ?? '',
      is_default: warehouse?.is_default ?? false,
    },
  })

  const create = usePostWarehousesCreate<ApiError>()
  const update = usePutWarehousesUpdate<ApiError>()
  const mutation = warehouse ? update : create

  const onSubmit = handleSubmit((values) => {
    const data = { code: values.code, name: values.name, address: blankToNull(values.address), is_default: values.is_default }

    if (warehouse) {
      update.mutate({ companyId, warehouseId: warehouse.id, data }, { onSuccess })
    } else {
      create.mutate({ companyId, data }, { onSuccess })
    }
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="warehouse-code">Código</Label>
        <Input id="warehouse-code" {...register('code')} />
        {errors.code && <p className="text-destructive text-sm">{errors.code.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="warehouse-name">Nome</Label>
        <Input id="warehouse-name" {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="warehouse-address">Morada</Label>
        <Input id="warehouse-address" {...register('address')} />
      </div>

      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" className="border-input size-4 rounded" {...register('is_default')} />
        Armazém principal (torna-se o único armazém principal da empresa)
      </label>

      {mutation.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(mutation.error, { 403: 'Sem permissão para gerir armazéns.' })}
        </p>
      )}

      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'A guardar…' : warehouse ? 'Guardar' : 'Criar armazém'}
      </Button>
    </form>
  )
}
