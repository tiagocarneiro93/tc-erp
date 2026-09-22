import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetSeriesListQueryOptions,
  useGetDocumentTypesList,
  useGetSeriesList,
  usePostSeriesActivate,
  usePostSeriesCreate,
  type GetSeriesList200ItemsItem,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/data-table/DataTable'
import { apiErrorMessage } from '@/lib/api-error'

const STATUS_LABELS: Record<string, string> = {
  draft: 'Rascunho',
  active: 'Ativa',
  finished: 'Terminada',
  cancelled: 'Cancelada',
}

/**
 * docs/plans/phase-2.md task 2.2's own screen: a series must be created
 * and activated before anything can be issued on it (`Series::canIssue()`).
 * Not one of task 2.11's named screens, but load-bearing for its own
 * accept criteria — without this, the document editor and receipt form
 * have nothing to offer in their series pickers.
 *
 * docs/plans/phase-3.md task 3.1: activating no longer takes a
 * manually-entered validation code — "Ativar" calls AT directly
 * (`registarSerie`) and stores whatever code AT hands back, surfacing
 * AT's own rejection message on failure.
 */
export function SeriesList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetSeriesList(companyId)
  const { data: documentTypesResponse } = useGetDocumentTypesList()
  const create = usePostSeriesCreate()
  const activate = usePostSeriesActivate<ApiError>()
  const [creating, setCreating] = useState(false)
  const [newDocumentType, setNewDocumentType] = useState('')
  const [newCode, setNewCode] = useState('')
  const [activationError, setActivationError] = useState<string | null>(null)

  const invalidate = () => queryClient.invalidateQueries({ queryKey: getGetSeriesListQueryOptions(companyId).queryKey })

  const handleCreate = () => {
    if ('' === newDocumentType || '' === newCode) {
      return
    }

    create.mutate(
      { companyId, data: { document_type: newDocumentType, code: newCode } },
      {
        onSuccess: () => {
          setCreating(false)
          setNewDocumentType('')
          setNewCode('')
          void invalidate()
        },
      },
    )
  }

  const handleActivate = (seriesId: string) => {
    setActivationError(null)

    activate.mutate(
      { companyId, seriesId },
      {
        onSuccess: () => void invalidate(),
        onError: (error) =>
          setActivationError(
            apiErrorMessage(error, {
              403: 'Sem permissão para gerir séries.',
              422: 'A AT rejeitou o registo da série.',
            }),
          ),
      },
    )
  }

  const columns: ColumnDef<GetSeriesList200ItemsItem>[] = [
    { accessorKey: 'document_type', header: 'Tipo' },
    { accessorKey: 'code', header: 'Código' },
    {
      id: 'status',
      header: 'Estado',
      cell: ({ row }) => (
        <Badge variant={'active' === row.original.status ? 'outline' : 'secondary'}>
          {STATUS_LABELS[row.original.status ?? ''] ?? row.original.status}
        </Badge>
      ),
    },
    { accessorKey: 'last_number', header: 'Último número' },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) =>
        'draft' === row.original.status && row.original.id ? (
          <Button
            variant="ghost"
            size="sm"
            disabled={activate.isPending}
            onClick={() => row.original.id && handleActivate(row.original.id)}
          >
            {activate.isPending ? 'A ativar…' : 'Ativar'}
          </Button>
        ) : null,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Séries</h1>
        <Button onClick={() => setCreating(true)}>Nova série</Button>
      </div>

      {activationError && (
        <p className="text-destructive text-sm" role="alert">
          {activationError}
        </p>
      )}

      <DataTable
        columns={columns}
        data={data?.items ?? []}
        isLoading={isLoading}
        emptyMessage="Nenhuma série criada."
      />

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Nova série</DialogTitle>
          </DialogHeader>
          <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="series-document-type">Tipo de documento</Label>
              <Select value={newDocumentType} onValueChange={setNewDocumentType}>
                <SelectTrigger id="series-document-type">
                  <SelectValue placeholder="Escolha o tipo" />
                </SelectTrigger>
                <SelectContent>
                  {(documentTypesResponse?.items ?? []).map((type) => (
                    <SelectItem key={type.code} value={type.code ?? ''}>
                      {type.code} — {type.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="series-code">Código</Label>
              <Input id="series-code" value={newCode} onChange={(event) => setNewCode(event.target.value)} />
            </div>
            <Button onClick={handleCreate} disabled={create.isPending}>
              {create.isPending ? 'A criar…' : 'Criar série'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
