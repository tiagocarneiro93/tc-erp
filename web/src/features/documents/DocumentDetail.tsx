import { useNavigate } from '@tanstack/react-router'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetDocumentsGetQueryOptions,
  useGetDocumentsGet,
  usePostDocumentsCancel,
  usePostDocumentsConvert,
  usePostDocumentsCreditNote,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiErrorMessage } from '@/lib/api-error'
import { formatMoney, formatQuantity } from '@/lib/format/decimal'

const STATUS_LABELS: Record<string, string> = { N: 'Normal', A: 'Anulado', F: 'Totalmente convertido' }

/**
 * docs/plans/phase-2.md task 2.11: read-only view of an issued document
 * (drafts are the only thing this app ever lets someone edit — CLAUDE.md
 * "issued fiscal data is immutable"), with the credit-note (task 2.7),
 * convert (task 2.8) and cancel (task 2.10) actions this document type
 * actually supports. Which actions actually apply *right now* — not just
 * "this kind of document can, in general" — comes straight from the
 * backend (`can_cancel`/`can_credit_note`/`convert_targets`, added after
 * the owner found a cancelled-in-vain "Anular" button): CLAUDE.md forbids
 * re-deriving fiscal eligibility rules here.
 */
export function DocumentDetail({ companyId, documentId }: { companyId: string; documentId: string }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: document, isLoading } = useGetDocumentsGet(companyId, documentId)
  const [convertOpen, setConvertOpen] = useState(false)
  const [convertTarget, setConvertTarget] = useState<string>('')
  const [actionError, setActionError] = useState<string | null>(null)

  const creditNote = usePostDocumentsCreditNote<ApiError>()
  const convert = usePostDocumentsConvert<ApiError>()
  const cancel = usePostDocumentsCancel<ApiError>()

  if (isLoading || !document) {
    return <p className="text-muted-foreground text-sm">A carregar…</p>
  }

  const convertTargets = document.convert_targets ?? []
  const canCreditNote = document.can_credit_note ?? false
  const canConvert = convertTargets.length > 0
  const canCancel = document.can_cancel ?? false

  const openConvertDialog = () => {
    setConvertTarget(convertTargets[0] ?? '')
    setConvertOpen(true)
  }

  const handleCreditNote = () => {
    setActionError(null)
    creditNote.mutate(
      { companyId, documentId, data: {} },
      {
        onSuccess: (result) => {
          if (result.id) {
            void navigate({ to: '/c/$companyId/documents/drafts/$draftId', params: { companyId, draftId: result.id } })
          }
        },
        onError: (error) =>
          setActionError(
            apiErrorMessage(error, {
              403: 'Sem permissão para emitir documentos.',
              422: 'Não é possível emitir uma nota de crédito.',
            }),
          ),
      },
    )
  }

  const handleConvert = () => {
    setActionError(null)
    convert.mutate(
      { companyId, documentId, data: { document_type: convertTarget } },
      {
        onSuccess: (result) => {
          setConvertOpen(false)

          if (result.id) {
            void navigate({ to: '/c/$companyId/documents/drafts/$draftId', params: { companyId, draftId: result.id } })
          }
        },
        onError: (error) =>
          setActionError(
            apiErrorMessage(error, {
              403: 'Sem permissão para emitir documentos.',
              422: 'Não é possível converter este documento.',
            }),
          ),
      },
    )
  }

  const handleCancel = () => {
    setActionError(null)

    const reason = window.prompt('Motivo da anulação (opcional):')

    if (null === reason) {
      return
    }

    cancel.mutate(
      { companyId, documentId, data: { reason: '' === reason ? null : reason } },
      {
        onSuccess: () =>
          void queryClient.invalidateQueries({
            queryKey: getGetDocumentsGetQueryOptions(companyId, documentId).queryKey,
          }),
        onError: (error) =>
          setActionError(
            apiErrorMessage(error, {
              403: 'Sem permissão para anular documentos.',
              422: 'Não é possível anular este documento.',
            }),
          ),
      },
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">{document.document_no}</h1>
          <Badge variant={'N' === document.status ? 'outline' : 'secondary'}>
            {STATUS_LABELS[document.status ?? ''] ?? document.status}
          </Badge>
        </div>
        <div className="flex gap-2">
          {canCreditNote && (
            <Button variant="outline" onClick={handleCreditNote} disabled={creditNote.isPending}>
              Nota de crédito
            </Button>
          )}
          {canConvert && (
            <Button variant="outline" onClick={openConvertDialog}>
              Converter
            </Button>
          )}
          {canCancel && (
            <Button variant="destructive" onClick={handleCancel} disabled={cancel.isPending}>
              Anular
            </Button>
          )}
        </div>
      </div>

      {actionError && (
        <p className="text-destructive text-sm" role="alert">
          {actionError}
        </p>
      )}

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Produto</TableHead>
            <TableHead>Qtd.</TableHead>
            <TableHead>Preço unit.</TableHead>
            <TableHead>IVA</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {(document.lines ?? []).map((line) => (
            <TableRow key={line.line_number}>
              <TableCell>
                {line.product_code} — {line.product_description}
              </TableCell>
              <TableCell>{formatQuantity(line.quantity ?? '0')}</TableCell>
              <TableCell>{formatMoney(line.unit_price ?? '0')}</TableCell>
              <TableCell>{line.tax_code}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <p className="ml-auto text-base">
        Total: <span className="font-semibold">{formatMoney(document.gross_total ?? '0')}</span>
      </p>

      <Dialog open={convertOpen} onOpenChange={setConvertOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Converter documento</DialogTitle>
          </DialogHeader>
          <div className="flex flex-col gap-4">
            <Select value={convertTarget} onValueChange={setConvertTarget}>
              <SelectTrigger aria-label="Tipo de destino">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {convertTargets.map((target) => (
                  <SelectItem key={target} value={target}>
                    {target}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button onClick={handleConvert} disabled={convert.isPending}>
              {convert.isPending ? 'A converter…' : 'Converter'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
