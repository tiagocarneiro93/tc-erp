import { useNavigate } from '@tanstack/react-router'
import { useEffect, useRef, useState } from 'react'

import {
  postDocumentsIssue,
  useGetCompanyProfileGet,
  useGetCustomersList,
  useGetDraftsGet,
  useGetProductsList,
  useGetSeriesList,
  useGetTaxRatesList,
  usePostTaxCalculate,
  usePutDraftsUpdate,
  type GetDraftsGet200Calculated,
  type PostTaxCalculate200,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiErrorMessage } from '@/lib/api-error'
import { formatMoney } from '@/lib/format/decimal'

const NONE = '__none__'
const SELECTABLE_TAX_CODES = ['ISE', 'RED', 'INT', 'NOR'] as const
const TAX_CODE_LABELS: Record<string, string> = { ISE: 'Isento', RED: 'Reduzida', INT: 'Intermédia', NOR: 'Normal' }

interface EditableLine {
  key: string
  product_id: string | null
  product_code: string
  description: string
  product_type: string
  unit_code: string
  quantity: string
  unit_price: string
  tax_code: string
  exemption_reason_code: string
  discount_percent: string
  /** Preserved as-is from a conversion draft's prefill (task 2.8) — this editor never sets or edits it. */
  origin?: unknown
}

function blankLine(): EditableLine {
  return {
    key: crypto.randomUUID(),
    product_id: null,
    product_code: '',
    description: '',
    product_type: '',
    unit_code: '',
    quantity: '1',
    unit_price: '0.00',
    tax_code: 'NOR',
    exemption_reason_code: '',
    discount_percent: '',
  }
}

function linesFromPayload(payload: Record<string, unknown>): EditableLine[] {
  const rawLines = Array.isArray(payload.lines) ? payload.lines : []

  return rawLines.map((raw) => {
    const line = raw as Record<string, unknown>
    const discounts = Array.isArray(line.discounts) ? line.discounts : []
    const percentageDiscount = discounts.find(
      (d): d is { type: string; value: string } =>
        'object' === typeof d && null !== d && 'percentage' === (d as Record<string, unknown>).type,
    )

    return {
      key: crypto.randomUUID(),
      product_id: 'string' === typeof line.product_id ? line.product_id : null,
      product_code: 'string' === typeof line.product_code ? line.product_code : '',
      description: 'string' === typeof line.description ? line.description : '',
      product_type: 'string' === typeof line.product_type ? line.product_type : '',
      unit_code: 'string' === typeof line.unit_code ? line.unit_code : '',
      quantity: 'string' === typeof line.quantity ? line.quantity : '1',
      unit_price: 'string' === typeof line.unit_price ? line.unit_price : '0.00',
      tax_code: 'string' === typeof line.tax_code ? line.tax_code : 'NOR',
      exemption_reason_code: 'string' === typeof line.exemption_reason_code ? line.exemption_reason_code : '',
      discount_percent: percentageDiscount?.value ?? '',
      origin: line.origin,
    }
  })
}

function linesToPayload(lines: EditableLine[], taxRegion: string) {
  return lines.map((line) => ({
    product_id: line.product_id,
    product_code: line.product_code,
    description: line.description,
    product_type: line.product_type,
    unit_code: line.unit_code,
    quantity: line.quantity,
    unit_price: line.unit_price,
    tax_region: taxRegion,
    tax_code: line.tax_code,
    exemption_reason_code: '' === line.exemption_reason_code ? null : line.exemption_reason_code,
    discounts: '' === line.discount_percent ? [] : [{ type: 'percentage', value: line.discount_percent }],
    ...(undefined === line.origin ? {} : { origin: line.origin }),
  }))
}

/**
 * technical-scope.md §11/docs/plans/phase-2.md task 2.11: totals always
 * come from a live call to `/calculate` (task 2.1) as lines change, never
 * computed here — this component only reflects whatever the API returns.
 * Issuing first persists the current state (`PUT .../drafts/{id}`) and
 * only then calls `POST .../issue`, so what gets issued is always exactly
 * what was last shown on screen, not whatever an earlier auto-save wrote.
 */
export function DraftEditor({ companyId, draftId }: { companyId: string; draftId: string }) {
  const navigate = useNavigate()
  const { data: draft, isLoading } = useGetDraftsGet(companyId, draftId)
  const { data: companyProfile } = useGetCompanyProfileGet(companyId)
  const taxRegion = companyProfile?.fiscal_region ?? 'PT'
  const { data: seriesResponse } = useGetSeriesList(companyId)
  const { data: customersResponse } = useGetCustomersList(companyId, { limit: 200 })
  const { data: productsResponse } = useGetProductsList(companyId, { limit: 200, active: true })
  const { data: taxRatesResponse } = useGetTaxRatesList({ region: taxRegion })

  const [initializedFor, setInitializedFor] = useState<string | null>(null)
  const [seriesId, setSeriesId] = useState('')
  const [customerId, setCustomerId] = useState(NONE)
  const [pricingMode, setPricingMode] = useState<'net' | 'gross'>('net')
  const [roundingMethod, setRoundingMethod] = useState<'per_line' | 'per_group'>('per_line')
  const [lines, setLines] = useState<EditableLine[]>([])
  const [calculated, setCalculated] = useState<PostTaxCalculate200 | GetDraftsGet200Calculated>(null)
  const [issueError, setIssueError] = useState<string | null>(null)
  const [issuing, setIssuing] = useState(false)

  // "Adjusting state when a value changes" (react.dev) — setting state
  // here, during render rather than in a `useEffect`, is what avoids an
  // extra render pass once the draft query resolves.
  const initialized = initializedFor === draftId
  if (draft && !initialized) {
    const payload = (draft.payload ?? {}) as Record<string, unknown>
    setInitializedFor(draftId)
    setSeriesId(draft.series_id ?? '')
    setCustomerId(draft.customer_id ?? NONE)
    setPricingMode('gross' === payload.pricing_mode ? 'gross' : 'net')
    setRoundingMethod('per_group' === payload.rounding_method ? 'per_group' : 'per_line')
    setLines(linesFromPayload(payload))
    setCalculated(draft.calculated ?? null)
  }

  const calculate = usePostTaxCalculate<ApiError>()
  const calculateTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined)

  useEffect(() => {
    if (!initialized || 0 === lines.length) {
      return
    }

    clearTimeout(calculateTimer.current)
    calculateTimer.current = setTimeout(() => {
      calculate.mutate(
        {
          companyId,
          data: { pricing_mode: pricingMode, rounding_method: roundingMethod, lines: linesToPayload(lines, taxRegion) },
        },
        { onSuccess: setCalculated, onError: () => setCalculated(null) },
      )
    }, 400)

    return () => clearTimeout(calculateTimer.current)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lines, pricingMode, roundingMethod, initialized])

  const update = usePutDraftsUpdate<ApiError>()

  const products = productsResponse?.items ?? []
  const taxRates = taxRatesResponse?.items ?? []
  const activeSeries = (seriesResponse?.items ?? []).filter(
    (s) => s.document_type === draft?.document_type && s.can_issue,
  )

  const setLine = (key: string, patch: Partial<EditableLine>) => {
    setLines((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)))
  }

  const selectProduct = (key: string, productId: string) => {
    const product = products.find((p) => p.id === productId)

    if (!product) {
      return
    }

    const taxRate = taxRates.find((rate) => rate.id === product.tax_rate_id)

    setLine(key, {
      product_id: product.id ?? null,
      product_code: product.code ?? '',
      description: product.description ?? '',
      product_type: product.type ?? '',
      unit_code: product.unit_code ?? '',
      tax_code: taxRate?.code ?? 'NOR',
      exemption_reason_code: product.exemption_reason_code ?? '',
    })
  }

  const buildPayload = () => ({
    ...((draft?.payload ?? {}) as Record<string, unknown>),
    series_id: '' === seriesId ? null : seriesId,
    customer_id: NONE === customerId ? null : customerId,
    pricing_mode: pricingMode,
    rounding_method: roundingMethod,
    lines: linesToPayload(lines, taxRegion),
  })

  const handleIssue = async () => {
    setIssueError(null)
    setIssuing(true)

    try {
      await update.mutateAsync({ companyId, draftId, data: { payload: buildPayload() } })
      const issued = await postDocumentsIssue(companyId, draftId, {
        headers: { 'Idempotency-Key': crypto.randomUUID() },
      })

      if (issued.id) {
        await navigate({ to: '/c/$companyId/documents/$documentId', params: { companyId, documentId: issued.id } })
      }
    } catch (error) {
      setIssueError(
        error instanceof ApiError
          ? apiErrorMessage(error, {
              403: 'Sem permissão para emitir documentos.',
              404: 'Rascunho ou série não encontrados.',
              422: 'O rascunho tem erros — verifique a série, o cliente e as linhas.',
            })
          : 'Ocorreu um erro. Tente novamente.',
      )
    } finally {
      setIssuing(false)
    }
  }

  if (isLoading || !draft || !initialized) {
    return <p className="text-muted-foreground text-sm">A carregar…</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">Rascunho — {draft.document_type}</h1>
          {draft.mention && <p className="text-muted-foreground text-sm">{draft.mention}</p>}
        </div>
        <Button onClick={() => void handleIssue()} disabled={issuing || 0 === lines.length}>
          {issuing ? 'A emitir…' : 'Emitir documento'}
        </Button>
      </div>

      {issueError && (
        <p className="text-destructive text-sm" role="alert">
          {issueError}
        </p>
      )}

      <div className="grid grid-cols-4 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="draft-series">Série</Label>
          <Select value={seriesId} onValueChange={setSeriesId}>
            <SelectTrigger id="draft-series">
              <SelectValue placeholder="Escolha a série" />
            </SelectTrigger>
            <SelectContent>
              {activeSeries.map((series) => (
                <SelectItem key={series.id} value={series.id ?? ''}>
                  {series.code}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="draft-customer">Cliente</Label>
          <Select value={customerId} onValueChange={setCustomerId}>
            <SelectTrigger id="draft-customer">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={NONE}>Consumidor final</SelectItem>
              {(customersResponse?.items ?? []).map((customer) => (
                <SelectItem key={customer.id} value={customer.id ?? ''}>
                  {customer.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="draft-pricing-mode">Modo de preços</Label>
          <Select value={pricingMode} onValueChange={(value) => setPricingMode(value as 'net' | 'gross')}>
            <SelectTrigger id="draft-pricing-mode">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="net">Líquido</SelectItem>
              <SelectItem value="gross">Bruto</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="draft-rounding">Arredondamento</Label>
          <Select
            value={roundingMethod}
            onValueChange={(value) => setRoundingMethod(value as 'per_line' | 'per_group')}
          >
            <SelectTrigger id="draft-rounding">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="per_line">Por linha</SelectItem>
              <SelectItem value="per_group">Por grupo de taxa</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Produto</TableHead>
            <TableHead>Qtd.</TableHead>
            <TableHead>Preço unit.</TableHead>
            <TableHead>Desconto %</TableHead>
            <TableHead>IVA</TableHead>
            <TableHead />
          </TableRow>
        </TableHeader>
        <TableBody>
          {lines.map((line) => (
            <TableRow key={line.key}>
              <TableCell>
                <Select value={line.product_id ?? ''} onValueChange={(value) => selectProduct(line.key, value)}>
                  <SelectTrigger aria-label="Produto">
                    <SelectValue placeholder="Escolha o produto">{line.product_code || undefined}</SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    {products.map((product) => (
                      <SelectItem key={product.id} value={product.id ?? ''}>
                        {product.code} — {product.description}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </TableCell>
              <TableCell>
                <Input
                  aria-label="Quantidade"
                  value={line.quantity}
                  onChange={(event) => setLine(line.key, { quantity: event.target.value })}
                  className="w-24"
                />
              </TableCell>
              <TableCell>
                <Input
                  aria-label="Preço unitário"
                  value={line.unit_price}
                  onChange={(event) => setLine(line.key, { unit_price: event.target.value })}
                  className="w-28"
                />
              </TableCell>
              <TableCell>
                <Input
                  value={line.discount_percent}
                  onChange={(event) => setLine(line.key, { discount_percent: event.target.value })}
                  className="w-20"
                />
              </TableCell>
              <TableCell>
                <Select value={line.tax_code} onValueChange={(value) => setLine(line.key, { tax_code: value })}>
                  <SelectTrigger className="w-32">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {SELECTABLE_TAX_CODES.map((code) => (
                      <SelectItem key={code} value={code}>
                        {TAX_CODE_LABELS[code]}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </TableCell>
              <TableCell>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setLines((current) => current.filter((l) => l.key !== line.key))}
                >
                  Remover
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Button
        variant="outline"
        size="sm"
        className="self-start"
        onClick={() => setLines((current) => [...current, blankLine()])}
      >
        Adicionar linha
      </Button>

      <div className="ml-auto flex flex-col items-end gap-1 text-sm">
        {calculate.isPending && <p className="text-muted-foreground">A calcular…</p>}
        {calculated && (
          <>
            <p>
              Total líquido:{' '}
              <span className="font-medium">{formatMoney((calculated as PostTaxCalculate200).net_total ?? '0')}</span>
            </p>
            <p>
              Total de imposto:{' '}
              <span className="font-medium">{formatMoney((calculated as PostTaxCalculate200).tax_total ?? '0')}</span>
            </p>
            <p className="text-base" data-testid="draft-total">
              Total:{' '}
              <span className="font-semibold">
                {formatMoney((calculated as PostTaxCalculate200).gross_total ?? '0')}
              </span>
            </p>
          </>
        )}
        {!calculated && !calculate.isPending && <p className="text-muted-foreground">Sem totais calculados ainda.</p>}
      </div>
    </div>
  )
}
