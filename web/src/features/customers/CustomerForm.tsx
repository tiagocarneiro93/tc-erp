import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostCustomersCreate, usePutCustomersUpdate, type GetCustomersGet200 } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { PaymentTermsSelect } from '@/features/payment-terms/PaymentTermsSelect'
import { CountrySelect } from '@/features/reference-data/CountrySelect'
import { apiErrorMessage } from '@/lib/api-error'
import { blankToNull } from '@/lib/forms'

const schema = z.object({
  code: z.string().min(1, 'Introduza o código'),
  nif: z.string().min(1, 'Introduza o NIF'),
  name: z.string().min(1, 'Introduza o nome'),
  address: z.string().optional(),
  postal_code: z.string().optional(),
  city: z.string().optional(),
  country: z.string().min(1, 'Escolha o país'),
  email: z.union([z.string().email('Email inválido'), z.literal('')]).optional(),
  phone: z.string().optional(),
  payment_terms_id: z.string().optional(),
  is_final_consumer: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface CustomerFormProps {
  companyId: string
  customer?: GetCustomersGet200 & { id: string }
  onSuccess: () => void
}

export function CustomerForm({ companyId, customer, onSuccess }: CustomerFormProps) {
  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: customer?.code ?? '',
      nif: customer?.nif ?? '',
      name: customer?.name ?? '',
      address: customer?.address ?? '',
      postal_code: customer?.postal_code ?? '',
      city: customer?.city ?? '',
      country: customer?.country ?? 'PT',
      email: customer?.email ?? '',
      phone: customer?.phone ?? '',
      payment_terms_id: customer?.payment_terms_id ?? '',
      is_final_consumer: customer?.is_final_consumer ?? false,
    },
  })

  const create = usePostCustomersCreate<ApiError>()
  const update = usePutCustomersUpdate<ApiError>()
  const mutation = customer ? update : create

  const onSubmit = handleSubmit((values) => {
    const data = {
      code: values.code,
      nif: values.nif,
      name: values.name,
      address: blankToNull(values.address),
      postal_code: blankToNull(values.postal_code),
      city: blankToNull(values.city),
      country: values.country,
      email: blankToNull(values.email),
      phone: blankToNull(values.phone),
      payment_terms_id: blankToNull(values.payment_terms_id),
      is_final_consumer: values.is_final_consumer,
    }

    if (customer) {
      update.mutate({ companyId, customerId: customer.id, data }, { onSuccess })
    } else {
      create.mutate({ companyId, data }, { onSuccess })
    }
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-code">Código</Label>
          <Input id="customer-code" {...register('code')} />
          {errors.code && <p className="text-destructive text-sm">{errors.code.message}</p>}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-nif">NIF</Label>
          <Input id="customer-nif" {...register('nif')} />
          {errors.nif && <p className="text-destructive text-sm">{errors.nif.message}</p>}
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="customer-name">Nome</Label>
        <Input id="customer-name" {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="customer-address">Morada</Label>
        <Input id="customer-address" {...register('address')} />
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-postal-code">Código postal</Label>
          <Input id="customer-postal-code" {...register('postal_code')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-city">Localidade</Label>
          <Input id="customer-city" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-country">País</Label>
          <Controller
            control={control}
            name="country"
            render={({ field }) => <CountrySelect id="customer-country" value={field.value} onValueChange={field.onChange} />}
          />
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-email">Email</Label>
          <Input id="customer-email" type="email" {...register('email')} />
          {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-phone">Telefone</Label>
          <Input id="customer-phone" {...register('phone')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="customer-payment-terms">Prazo de pagamento</Label>
          <Controller
            control={control}
            name="payment_terms_id"
            render={({ field }) => (
              <PaymentTermsSelect id="customer-payment-terms" companyId={companyId} value={field.value ?? ''} onValueChange={field.onChange} />
            )}
          />
        </div>
      </div>

      {!customer && (
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" className="border-input size-4 rounded" {...register('is_final_consumer')} />
          Consumidor final
        </label>
      )}

      {mutation.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(mutation.error, { 422: 'NIF ou país inválido.', 403: 'Sem permissão para gerir clientes.' })}
        </p>
      )}

      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'A guardar…' : customer ? 'Guardar' : 'Criar cliente'}
      </Button>
    </form>
  )
}
