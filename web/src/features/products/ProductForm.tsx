import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'

import {
  useGetCompanyProfileGet,
  useGetExemptionReasonsList,
  useGetProductFamiliesList,
  useGetTaxRatesList,
  useGetUnitsList,
  usePostProductsCreate,
  usePutProductsUpdate,
  type GetProductsGet200,
  type GetTaxRatesList200ItemsItem,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { apiErrorMessage } from '@/lib/api-error'
import { blankToNull } from '@/lib/forms'

const PRODUCT_TYPES = ['P', 'S', 'O', 'E', 'I'] as const

// CLAUDE.md: never guess AT formats. These letter->meaning glosses are
// well-known SAF-T PT terminology but are NOT yet cited from a
// docs/legal/ source — verify against the official SAF-T PT technical
// spec before treating these labels as authoritative.
const PRODUCT_TYPE_LABELS: Record<(typeof PRODUCT_TYPES)[number], string> = {
  P: 'Produto',
  S: 'Serviço',
  O: 'Outro',
  E: 'Imposto especial de consumo',
  I: 'Outro imposto/taxa',
}
const NONE = '__none__'
const EXEMPT_CODE = 'ISE'

// A product only ever needs one of the four VAT categories that apply to
// sales — never SAF-T's `OUT` (out of scope of VAT, used elsewhere, not on
// a product's own master data). Ordered the way a user thinks about them,
// least to most taxed, exempt first since it needs the extra "motivo"
// field right next to it.
const SELECTABLE_TAX_CODES = ['ISE', 'RED', 'INT', 'NOR'] as const
const TAX_CODE_LABELS: Record<string, string> = { ISE: 'Isento', RED: 'Reduzida', INT: 'Intermédia', NOR: 'Normal' }

const schema = z.object({
  code: z.string().min(1, 'Introduza o código'),
  description: z.string().min(1, 'Introduza a descrição'),
  type: z.string().min(1, 'Escolha o tipo'),
  kind: z.enum(['simple', 'kit']),
  unit_code: z.string().min(1, 'Escolha a unidade'),
  barcode: z.string().optional(),
  family_id: z.string(),
  tax_rate_id: z.string().min(1, 'Escolha a taxa de IVA'),
  exemption_reason_code: z.string(),
  track_stock: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface ProductFormProps {
  companyId: string
  product?: GetProductsGet200 & { id: string }
  onSuccess: () => void
}

export function ProductForm({ companyId, product, onSuccess }: ProductFormProps) {
  const { data: families } = useGetProductFamiliesList(companyId)
  const { data: companyProfile } = useGetCompanyProfileGet(companyId)
  const fiscalRegion = companyProfile?.fiscal_region ?? 'PT'
  const { data: taxRatesResponse } = useGetTaxRatesList({ region: fiscalRegion })
  const { data: exemptionReasons } = useGetExemptionReasonsList()
  const { data: units } = useGetUnitsList()

  // Only this company's own region, and only the four VAT categories a
  // product can carry — never every region's rows at once, and never
  // SAF-T's `OUT`.
  const taxRates = (taxRatesResponse?.items ?? [])
    .filter((rate): rate is GetTaxRatesList200ItemsItem & { code: string } =>
      (SELECTABLE_TAX_CODES as readonly string[]).includes(rate.code ?? ''),
    )
    .sort(
      (a, b) =>
        (SELECTABLE_TAX_CODES as readonly string[]).indexOf(a.code) -
        (SELECTABLE_TAX_CODES as readonly string[]).indexOf(b.code),
    )

  const {
    register,
    control,
    handleSubmit,
    setError,
    watch,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: product?.code ?? '',
      description: product?.description ?? '',
      type: product?.type ?? 'P',
      kind: (product?.kind as 'simple' | 'kit' | undefined) ?? 'simple',
      unit_code: product?.unit_code ?? '',
      barcode: product?.barcode ?? '',
      family_id: product?.family_id ?? NONE,
      tax_rate_id: product?.tax_rate_id ?? '',
      exemption_reason_code: product?.exemption_reason_code ?? NONE,
      track_stock: product?.track_stock ?? false,
    },
  })

  const create = usePostProductsCreate<ApiError>()
  const update = usePutProductsUpdate<ApiError>()
  const mutation = product ? update : create

  const selectedTaxRateId = watch('tax_rate_id')
  const isExempt = taxRates.some((rate) => rate.id === selectedTaxRateId && EXEMPT_CODE === rate.code)

  const onSubmit = handleSubmit((values) => {
    if (isExempt && NONE === values.exemption_reason_code) {
      setError('exemption_reason_code', { message: 'Obrigatório quando a taxa é Isento.' })

      return
    }

    const data = {
      code: values.code,
      description: values.description,
      type: values.type,
      kind: values.kind,
      unit_code: values.unit_code,
      barcode: blankToNull(values.barcode),
      family_id: NONE === values.family_id ? null : values.family_id,
      tax_rate_id: values.tax_rate_id,
      exemption_reason_code: NONE === values.exemption_reason_code ? null : values.exemption_reason_code,
      track_stock: values.track_stock,
    }

    if (product) {
      update.mutate({ companyId, productId: product.id, data }, { onSuccess })
    } else {
      create.mutate({ companyId, data }, { onSuccess })
    }
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="product-code">Código</Label>
          <Input id="product-code" {...register('code')} />
          {errors.code && <p className="text-destructive text-sm">{errors.code.message}</p>}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="product-description">Descrição</Label>
          <Input id="product-description" {...register('description')} />
          {errors.description && <p className="text-destructive text-sm">{errors.description.message}</p>}
        </div>
      </div>

      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="product-type">Tipo (SAF-T)</Label>
          <Controller
            control={control}
            name="type"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="product-type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {PRODUCT_TYPES.map((type) => (
                    <SelectItem key={type} value={type}>
                      {type} - {PRODUCT_TYPE_LABELS[type]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="product-kind">Género</Label>
          <Controller
            control={control}
            name="kind"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange} disabled={undefined !== product}>
                <SelectTrigger id="product-kind">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="simple">Simples</SelectItem>
                  <SelectItem value="kit">Kit</SelectItem>
                </SelectContent>
              </Select>
            )}
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="product-unit">Unidade</Label>
          <Controller
            control={control}
            name="unit_code"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="product-unit">
                  <SelectValue placeholder="Escolha a unidade" />
                </SelectTrigger>
                <SelectContent>
                  {(units?.items ?? []).map((unit) => (
                    <SelectItem key={unit.code} value={unit.code ?? ''}>
                      {unit.code} — {unit.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
          {errors.unit_code && <p className="text-destructive text-sm">{errors.unit_code.message}</p>}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="product-barcode">Código de barras</Label>
          <Input id="product-barcode" {...register('barcode')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="product-family">Família</Label>
          <Controller
            control={control}
            name="family_id"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="product-family">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NONE}>Nenhuma</SelectItem>
                  {(families?.items ?? []).map((family) => (
                    <SelectItem key={family.id} value={family.id ?? ''}>
                      {family.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="product-tax-rate">Taxa de IVA ({fiscalRegion})</Label>
          <Controller
            control={control}
            name="tax_rate_id"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="product-tax-rate">
                  <SelectValue placeholder="Escolha a taxa" />
                </SelectTrigger>
                <SelectContent>
                  {taxRates.map((rate) => (
                    <SelectItem key={rate.id} value={rate.id ?? ''}>
                      {TAX_CODE_LABELS[rate.code] ?? rate.code} — {rate.percentage}%
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
          {errors.tax_rate_id && <p className="text-destructive text-sm">{errors.tax_rate_id.message}</p>}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="product-exemption-reason">Motivo de isenção{isExempt && ' (obrigatório)'}</Label>
          <Controller
            control={control}
            name="exemption_reason_code"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id="product-exemption-reason" aria-invalid={!!errors.exemption_reason_code}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {!isExempt && <SelectItem value={NONE}>Nenhum</SelectItem>}
                  {(exemptionReasons?.items ?? []).map((reason) => (
                    <SelectItem key={reason.code} value={reason.code ?? ''}>
                      {reason.code} — {reason.description}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
          {errors.exemption_reason_code && (
            <p className="text-destructive text-sm">{errors.exemption_reason_code.message}</p>
          )}
        </div>
      </div>

      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" className="border-input size-4 rounded" {...register('track_stock')} />
        Controla stock
      </label>

      {mutation.isError && (
        <p className="text-destructive text-sm" role="alert">
          {apiErrorMessage(mutation.error, {
            403: 'Sem permissão para gerir produtos.',
            404: 'Família, taxa de IVA ou motivo de isenção não encontrado.',
            422: 'Verifique os dados introduzidos.',
          })}
        </p>
      )}

      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'A guardar…' : product ? 'Guardar' : 'Criar produto'}
      </Button>
    </form>
  )
}
