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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/data-table/DataTable'

const STATUS_LABELS: Record<string, string> = {
  draft: 'Rascunho',
  active: 'Ativa',
  finished: 'Terminada',
  cancelled: 'Cancelada',
}

/**
 * docs/plans/phase-2.md task 2.2's own screen: a series must be created
 * and, once the AT hands back its validation code (entered manually this
 * phase — the AT webservice call is Phase 3), activated before anything
 * can be issued on it (`Series::canIssue()`). Not one of task 2.11's
 * named screens, but load-bearing for its own accept criteria — without
 * this, the document editor and receipt form have nothing to offer in
 * their series pickers.
 */
export function SeriesList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetSeriesList(companyId)
  const { data: documentTypesResponse } = useGetDocumentTypesList()
  const create = usePostSeriesCreate()
  const activate = usePostSeriesActivate()
  const [creating, setCreating] = useState(false)
  const [newDocumentType, setNewDocumentType] = useState('')
  const [newCode, setNewCode] = useState('')
  const [activatingId, setActivatingId] = useState<string | null>(null)
  const [validationCode, setValidationCode] = useState('')

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

  const handleActivate = () => {
    if (!activatingId || '' === validationCode) {
      return
    }

    activate.mutate(
      { companyId, seriesId: activatingId, data: { validation_code: validationCode } },
      {
        onSuccess: () => {
          setActivatingId(null)
          setValidationCode('')
          void invalidate()
        },
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
        'draft' === row.original.status ? (
          <Button variant="ghost" size="sm" onClick={() => row.original.id && setActivatingId(row.original.id)}>
            Ativar
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

      <Dialog open={null !== activatingId} onOpenChange={(open) => !open && setActivatingId(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Ativar série</DialogTitle>
          </DialogHeader>
          <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="validation-code">Código de validação (AT)</Label>
              <Input
                id="validation-code"
                value={validationCode}
                onChange={(event) => setValidationCode(event.target.value)}
              />
            </div>
            <Button onClick={handleActivate} disabled={activate.isPending}>
              {activate.isPending ? 'A ativar…' : 'Ativar'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
