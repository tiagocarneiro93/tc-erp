import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'

import {
  useGetProductFamiliesList,
  usePostProductFamiliesCreate,
  usePutProductFamiliesUpdate,
  type GetProductFamiliesList200ItemsItem,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { apiErrorMessage } from '@/lib/api-error'

const NO_PARENT = '__none__'

const schema = z.object({
  name: z.string().min(1, 'Introduza o nome'),
  parent_id: z.string(),
})

type FormValues = z.infer<typeof schema>

interface ProductFamilyFormProps {
  companyId: string
  family?: GetProductFamiliesList200ItemsItem & { id: string }
  onSuccess: () => void
}

export function ProductFamilyForm({ companyId, family, onSuccess }: ProductFamilyFormProps) {
  const { data } = useGetProductFamiliesList(companyId)
  const candidateParents = (data?.items ?? []).filter((candidate) => candidate.id !== family?.id)

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: family?.name ?? '', parent_id: family?.parent_id ?? NO_PARENT },
  })

  const create = usePostProductFamiliesCreate<ApiError>()
  const update = usePutProductFamiliesUpdate<ApiError>()
  const mutation = family ? update : create

  const onSubmit = handleSubmit((values) => {
    const data = { name: values.name, parent_id: NO_PARENT === values.parent_id ? null : values.parent_id }

    if (family) {
      update.mutate({ companyId, familyId: family.id, data }, { onSuccess })
    } else {
      create.mutate({ companyId, data }, { onSuccess })
    }
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="family-name">Nome</Label>
        <Input id="family-name" {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="family-parent">Família-mãe</Label>
        <Controller
          control={control}
          name="parent_id"
          render={({ field }) => (
            <Select value={field.value} onValueChange={field.onChange}>
              <SelectTrigger id="family-parent">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NO_PARENT}>Nenhuma</SelectItem>
                {candidateParents.map((candidate) => (
                  <SelectItem key={candidate.id} value={candidate.id ?? ''}>
                    {candidate.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        />
      </div>

      {mutation.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(mutation.error, {
            422: 'Essa família-mãe criaria um ciclo.',
            403: 'Sem permissão para gerir produtos.',
          })}
        </p>
      )}

      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'A guardar…' : family ? 'Guardar' : 'Criar família'}
      </Button>
    </form>
  )
}
