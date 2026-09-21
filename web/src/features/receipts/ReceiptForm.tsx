import { useState } from 'react'

import { postReceiptsIssue, useGetCustomersList, useGetDocumentsList, useGetSeriesList } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiErrorMessage } from '@/lib/api-error'
import { formatMoney } from '@/lib/format/decimal'

const NONE = '__none__'

/**
 * docs/plans/phase-2.md task 2.9/2.11: a receipt allocates its total
 * across one or more open invoices. "Open" here is a display filter only
 * (`open_amount > 0`, `status === 'N'`) — which allocations are actually
 * allowed is decided server-side by `IssueReceiptHandler`, not here
 * (CLAUDE.md: no fiscal logic in the browser).
 */
export function ReceiptForm({ companyId, onSuccess }: { companyId: string; onSuccess: () => void }) {
  const { data: seriesResponse } = useGetSeriesList(companyId)
  const { data: customersResponse } = useGetCustomersList(companyId, { limit: 200 })
  const { data: documentsResponse } = useGetDocumentsList(companyId, { status: 'N' })

  const [seriesId, setSeriesId] = useState('')
  const [customerId, setCustomerId] = useState(NONE)
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [amounts, setAmounts] = useState<Record<string, string>>({})
  const [issuing, setIssuing] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const rgSeries = (seriesResponse?.items ?? []).filter((series) => 'RG' === series.document_type && series.can_issue)
  const openDocuments = (documentsResponse?.items ?? []).filter(
    (document) => 0 < parseFloat(document.open_amount ?? '0'),
  )

  const toggle = (documentId: string, openAmount: string) => {
    setAmounts((current) => {
      const next = { ...current }

      if (documentId in next) {
        delete next[documentId]
      } else {
        next[documentId] = openAmount
      }

      return next
    })
  }

  const total = Object.values(amounts).reduce((sum, amount) => sum + (parseFloat(amount) || 0), 0)

  const handleSubmit = async () => {
    setError(null)
    setIssuing(true)

    try {
      await postReceiptsIssue(
        companyId,
        {
          series_id: seriesId,
          customer_id: NONE === customerId ? null : customerId,
          payment_method: paymentMethod,
          allocations: Object.entries(amounts).map(([document_id, amount]) => ({ document_id, amount })),
        },
        { headers: { 'Idempotency-Key': crypto.randomUUID() } },
      )
      onSuccess()
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? apiErrorMessage(caught, {
              403: 'Sem permissão para emitir recibos.',
              404: 'Série ou documento não encontrados.',
              422: 'Verifique as alocações — podem exceder o saldo em aberto.',
            })
          : 'Ocorreu um erro. Tente novamente.',
      )
    } finally {
      setIssuing(false)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-3 gap-4">
        <div className="flex flex-col gap-2">
          <Label htmlFor="receipt-series">Série</Label>
          <Select value={seriesId} onValueChange={setSeriesId}>
            <SelectTrigger id="receipt-series">
              <SelectValue placeholder="Escolha a série" />
            </SelectTrigger>
            <SelectContent>
              {rgSeries.map((series) => (
                <SelectItem key={series.id} value={series.id ?? ''}>
                  {series.code}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="receipt-customer">Cliente</Label>
          <Select value={customerId} onValueChange={setCustomerId}>
            <SelectTrigger id="receipt-customer">
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
          <Label htmlFor="receipt-payment-method">Forma de pagamento</Label>
          <Input
            id="receipt-payment-method"
            value={paymentMethod}
            onChange={(event) => setPaymentMethod(event.target.value)}
          />
        </div>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead />
            <TableHead>Documento</TableHead>
            <TableHead>Saldo em aberto</TableHead>
            <TableHead>Valor a alocar</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {openDocuments.map((document) => (
            <TableRow key={document.id}>
              <TableCell>
                <input
                  type="checkbox"
                  checked={undefined !== document.id && document.id in amounts}
                  onChange={() => document.id && toggle(document.id, document.open_amount ?? '0')}
                />
              </TableCell>
              <TableCell>{document.document_no}</TableCell>
              <TableCell>{formatMoney(document.open_amount ?? '0')}</TableCell>
              <TableCell>
                {document.id && document.id in amounts && (
                  <Input
                    value={amounts[document.id]}
                    onChange={(event) =>
                      setAmounts((current) => ({ ...current, [document.id ?? '']: event.target.value }))
                    }
                    className="w-28"
                  />
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <p className="ml-auto text-sm">
        Total: <span className="font-semibold">{formatMoney(total.toFixed(2))}</span>
      </p>

      {error && (
        <p className="text-destructive text-sm" role="alert">
          {error}
        </p>
      )}

      <Button
        onClick={() => void handleSubmit()}
        disabled={issuing || '' === seriesId || 0 === Object.keys(amounts).length}
      >
        {issuing ? 'A emitir…' : 'Emitir recibo'}
      </Button>
    </div>
  )
}
