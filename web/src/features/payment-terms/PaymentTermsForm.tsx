import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import {
  usePostPaymentTermsCreate,
  usePutPaymentTermsUpdate,
  type GetPaymentTermsList200ItemsItem,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { apiErrorMessage } from '@/lib/api-error'

const schema = z.object({
  name: z.string().min(1, 'Introduza o nome'),
  days: z.number().int().min(0, 'O prazo não pode ser negativo'),
  is_default: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface PaymentTermsFormProps {
  companyId: string
  paymentTerms?: GetPaymentTermsList200ItemsItem & { id: string }
  onSuccess: () => void
}

export function PaymentTermsForm({ companyId, paymentTerms, onSuccess }: PaymentTermsFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: paymentTerms?.name ?? '',
      days: paymentTerms?.days ?? 0,
      is_default: paymentTerms?.is_default ?? false,
    },
  })

  const create = usePostPaymentTermsCreate<ApiError>()
  const update = usePutPaymentTermsUpdate<ApiError>()
  const mutation = paymentTerms ? update : create

  const onSubmit = handleSubmit((values) => {
    const data = { name: values.name, days: values.days, is_default: values.is_default }

    if (paymentTerms) {
      update.mutate({ companyId, paymentTermsId: paymentTerms.id, data }, { onSuccess })
    } else {
      create.mutate({ companyId, data }, { onSuccess })
    }
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="payment-terms-name">Nome</Label>
        <Input id="payment-terms-name" {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="payment-terms-days">Dias</Label>
        <Input id="payment-terms-days" type="number" min={0} {...register('days', { valueAsNumber: true })} />
        {errors.days && <p className="text-destructive text-sm">{errors.days.message}</p>}
      </div>

      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" className="border-input size-4 rounded" {...register('is_default')} />
        Prazo por omissão (torna-se o único prazo por omissão da empresa)
      </label>

      {mutation.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(mutation.error, { 403: 'Sem permissão para gerir prazos de pagamento.' })}
        </p>
      )}

      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'A guardar…' : paymentTerms ? 'Guardar' : 'Criar prazo de pagamento'}
      </Button>
    </form>
  )
}
