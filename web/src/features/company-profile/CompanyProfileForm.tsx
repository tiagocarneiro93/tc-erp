import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'

import { getGetCompanyProfileGetQueryKey, useGetCompanyProfileGet, usePutCompanyProfileUpdate } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CountrySelect } from '@/features/reference-data/CountrySelect'
import { apiErrorMessage } from '@/lib/api-error'
import { blankToNull } from '@/lib/forms'

const FISCAL_REGIONS = ['PT', 'PT-AC', 'PT-MA'] as const

const schema = z.object({
  nif: z.string().min(1, 'Introduza o NIF'),
  legal_name: z.string().min(1, 'Introduza a firma'),
  commercial_name: z.string().optional(),
  address: z.string().optional(),
  postal_code: z.string().optional(),
  city: z.string().optional(),
  country: z.string().min(1, 'Escolha o país'),
  share_capital: z.string().optional(),
  registry_office: z.string().optional(),
  email: z.union([z.string().email('Email inválido'), z.literal('')]).optional(),
  phone: z.string().optional(),
  fiscal_region: z.string().min(1, 'Escolha a região fiscal'),
  vat_regime: z.string().min(1, 'Introduza o regime de IVA'),
  cash_vat: z.boolean(),
})

type FormValues = z.infer<typeof schema>

export function CompanyProfileForm({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data: profile, isLoading } = useGetCompanyProfileGet(companyId)

  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    values: profile && {
      nif: profile.nif ?? '',
      legal_name: profile.legal_name ?? '',
      commercial_name: profile.commercial_name ?? '',
      address: profile.address ?? '',
      postal_code: profile.postal_code ?? '',
      city: profile.city ?? '',
      country: profile.country ?? 'PT',
      share_capital: profile.share_capital ?? '',
      registry_office: profile.registry_office ?? '',
      email: profile.email ?? '',
      phone: profile.phone ?? '',
      fiscal_region: profile.fiscal_region ?? 'PT',
      vat_regime: profile.vat_regime ?? '',
      cash_vat: profile.cash_vat ?? false,
    },
  })

  const update = usePutCompanyProfileUpdate<ApiError>()

  const onSubmit = handleSubmit((values) => {
    update.mutate(
      {
        companyId,
        data: {
          nif: values.nif,
          legal_name: values.legal_name,
          commercial_name: blankToNull(values.commercial_name),
          address: blankToNull(values.address),
          postal_code: blankToNull(values.postal_code),
          city: blankToNull(values.city),
          country: values.country,
          share_capital: blankToNull(values.share_capital),
          registry_office: blankToNull(values.registry_office),
          email: blankToNull(values.email),
          phone: blankToNull(values.phone),
          fiscal_region: values.fiscal_region,
          vat_regime: values.vat_regime,
          cash_vat: values.cash_vat,
        },
      },
      { onSuccess: () => queryClient.invalidateQueries({ queryKey: getGetCompanyProfileGetQueryKey(companyId) }) },
    )
  })

  if (isLoading) {
    return <p className="text-muted-foreground text-sm">A carregar…</p>
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-nif">NIF</Label>
          <Input id="profile-nif" {...register('nif')} />
          {errors.nif && <p className="text-destructive text-sm">{errors.nif.message}</p>}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-legal-name">Firma</Label>
          <Input id="profile-legal-name" {...register('legal_name')} />
          {errors.legal_name && <p className="text-destructive text-sm">{errors.legal_name.message}</p>}
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="profile-commercial-name">Nome comercial</Label>
        <Input id="profile-commercial-name" {...register('commercial_name')} />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="profile-address">Morada</Label>
        <Input id="profile-address" {...register('address')} />
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-postal-code">Código postal</Label>
          <Input id="profile-postal-code" {...register('postal_code')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-city">Localidade</Label>
          <Input id="profile-city" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-country">País</Label>
          <Controller
            control={control}
            name="country"
            render={({ field }) => <CountrySelect id="profile-country" value={field.value} onValueChange={field.onChange} />}
          />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-share-capital">Capital social</Label>
          <Input id="profile-share-capital" {...register('share_capital')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-registry-office">Conservatória do registo comercial</Label>
          <Input id="profile-registry-office" {...register('registry_office')} />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-email">Email</Label>
          <Input id="profile-email" type="email" {...register('email')} />
          {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-phone">Telefone</Label>
          <Input id="profile-phone" {...register('phone')} />
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-fiscal-region">Região fiscal</Label>
          <Controller
            control={control}
            name="fiscal_region"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="profile-fiscal-region">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {FISCAL_REGIONS.map((region) => (
                    <SelectItem key={region} value={region}>
                      {region}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="profile-vat-regime">Regime de IVA</Label>
          <Input id="profile-vat-regime" {...register('vat_regime')} />
          {errors.vat_regime && <p className="text-destructive text-sm">{errors.vat_regime.message}</p>}
        </div>
        <label className="flex items-center gap-2 pb-2 text-sm">
          <input type="checkbox" className="border-input size-4 rounded" {...register('cash_vat')} />
          IVA de caixa
        </label>
      </div>

      {update.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(update.error, { 422: 'Verifique os dados introduzidos.', 403: 'Sem permissão para gerir a empresa.' })}
        </p>
      )}

      {update.isSuccess && <p className="text-sm text-green-700">Dados guardados.</p>}

      <Button type="submit" disabled={update.isPending} className="w-fit">
        {update.isPending ? 'A guardar…' : 'Guardar'}
      </Button>
    </form>
  )
}
