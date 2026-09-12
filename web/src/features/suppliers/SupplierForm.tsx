import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostSuppliersCreate, usePutSuppliersUpdate, type GetSuppliersGet200 } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
  payment_terms_days: z.string().optional(),
})

type FormValues = z.infer<typeof schema>

interface SupplierFormProps {
  companyId: string
  supplier?: GetSuppliersGet200 & { id: string }
  onSuccess: () => void
}

export function SupplierForm({ companyId, supplier, onSuccess }: SupplierFormProps) {
  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: supplier?.code ?? '',
      nif: supplier?.nif ?? '',
      name: supplier?.name ?? '',
      address: supplier?.address ?? '',
      postal_code: supplier?.postal_code ?? '',
      city: supplier?.city ?? '',
      country: supplier?.country ?? 'PT',
      email: supplier?.email ?? '',
      phone: supplier?.phone ?? '',
      payment_terms_days: supplier?.payment_terms_days?.toString() ?? '',
    },
  })

  const create = usePostSuppliersCreate<ApiError>()
  const update = usePutSuppliersUpdate<ApiError>()
  const mutation = supplier ? update : create

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
      payment_terms_days: values.payment_terms_days ? Number(values.payment_terms_days) : null,
    }

    if (supplier) {
      update.mutate({ companyId, supplierId: supplier.id, data }, { onSuccess })
    } else {
      create.mutate({ companyId, data }, { onSuccess })
    }
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-code">Código</Label>
          <Input id="supplier-code" {...register('code')} />
          {errors.code && <p className="text-destructive text-sm">{errors.code.message}</p>}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-nif">NIF</Label>
          <Input id="supplier-nif" {...register('nif')} />
          {errors.nif && <p className="text-destructive text-sm">{errors.nif.message}</p>}
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="supplier-name">Nome</Label>
        <Input id="supplier-name" {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="supplier-address">Morada</Label>
        <Input id="supplier-address" {...register('address')} />
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-postal-code">Código postal</Label>
          <Input id="supplier-postal-code" {...register('postal_code')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-city">Localidade</Label>
          <Input id="supplier-city" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-country">País</Label>
          <Controller
            control={control}
            name="country"
            render={({ field }) => <CountrySelect id="supplier-country" value={field.value} onValueChange={field.onChange} />}
          />
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-email">Email</Label>
          <Input id="supplier-email" type="email" {...register('email')} />
          {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-phone">Telefone</Label>
          <Input id="supplier-phone" {...register('phone')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="supplier-payment-terms">Prazo de pagamento (dias)</Label>
          <Input id="supplier-payment-terms" type="number" min={0} {...register('payment_terms_days')} />
        </div>
      </div>

      {mutation.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(mutation.error, { 422: 'NIF ou país inválido.', 403: 'Sem permissão para gerir fornecedores.' })}
        </p>
      )}

      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'A guardar…' : supplier ? 'Guardar' : 'Criar fornecedor'}
      </Button>
    </form>
  )
}
