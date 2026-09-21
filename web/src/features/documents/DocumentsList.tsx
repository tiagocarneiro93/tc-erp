import type { ColumnDef } from '@tanstack/react-table'
import { Link, useNavigate } from '@tanstack/react-router'
import { useState } from 'react'

import {
  useDeleteDraftsDelete,
  useGetDocumentTypesList,
  useGetDocumentsList,
  useGetDraftsList,
  usePostDraftsCreate,
  type GetDocumentsList200ItemsItem,
  type GetDraftsList200ItemsItem,
} from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/data-table/DataTable'
import { formatMoney } from '@/lib/format/decimal'

const STATUS_LABELS: Record<string, string> = { N: 'Normal', A: 'Anulado', F: 'Convertido' }

/**
 * docs/plans/phase-2.md task 2.11: issued documents are read-only here
 * (CLAUDE.md "issued fiscal data is immutable") — editing only ever
 * happens on a draft, listed separately above the issued-document table
 * since drafts live in a different table entirely (task 2.4).
 */
export function DocumentsList({ companyId }: { companyId: string }) {
  const navigate = useNavigate()
  const { data: draftsResponse } = useGetDraftsList(companyId)
  const { data: documentsResponse, isLoading } = useGetDocumentsList(companyId, {})
  const { data: documentTypesResponse } = useGetDocumentTypesList()
  const deleteDraft = useDeleteDraftsDelete()
  const createDraft = usePostDraftsCreate()
  const [creating, setCreating] = useState(false)
  const [newDocumentType, setNewDocumentType] = useState('')

  const creatableTypes = (documentTypesResponse?.items ?? []).filter((type) => 'RG' !== type.code)

  const handleCreate = () => {
    if ('' === newDocumentType) {
      return
    }

    createDraft.mutate(
      { companyId, data: { document_type: newDocumentType, payload: { lines: [] } } },
      {
        onSuccess: (result) => {
          setCreating(false)

          if (result.id) {
            void navigate({ to: '/c/$companyId/documents/drafts/$draftId', params: { companyId, draftId: result.id } })
          }
        },
      },
    )
  }

  const documentColumns: ColumnDef<GetDocumentsList200ItemsItem>[] = [
    {
      accessorKey: 'document_no',
      header: 'N.º',
      cell: ({ row }) => (
        <Link
          to="/c/$companyId/documents/$documentId"
          params={{ companyId, documentId: row.original.id ?? '' }}
          className="hover:underline"
        >
          {row.original.document_no}
        </Link>
      ),
    },
    { accessorKey: 'customer_name', header: 'Cliente' },
    {
      id: 'status',
      header: 'Estado',
      cell: ({ row }) => (
        <Badge variant={'N' === row.original.status ? 'outline' : 'secondary'}>
          {STATUS_LABELS[row.original.status ?? ''] ?? row.original.status}
        </Badge>
      ),
    },
    { id: 'gross_total', header: 'Total', cell: ({ row }) => formatMoney(row.original.gross_total ?? '0') },
  ]

  const draftColumns: ColumnDef<GetDraftsList200ItemsItem>[] = [
    { accessorKey: 'document_type', header: 'Tipo' },
    {
      id: 'updated_at',
      header: 'Atualizado',
      cell: ({ row }) => new Date(row.original.updated_at ?? '').toLocaleString('pt-PT'),
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) => (
        <div className="flex justify-end gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={() =>
              void navigate({
                to: '/c/$companyId/documents/drafts/$draftId',
                params: { companyId, draftId: row.original.id ?? '' },
              })
            }
          >
            Continuar
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => row.original.id && deleteDraft.mutate({ companyId, draftId: row.original.id })}
          >
            Eliminar
          </Button>
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-8">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Documentos</h1>
        <Button onClick={() => setCreating(true)}>Novo documento</Button>
      </div>

      {0 < (draftsResponse?.items ?? []).length && (
        <div className="flex flex-col gap-2">
          <h2 className="text-muted-foreground text-sm font-medium">Rascunhos</h2>
          <DataTable columns={draftColumns} data={draftsResponse?.items ?? []} />
        </div>
      )}

      <div className="flex flex-col gap-2">
        <h2 className="text-muted-foreground text-sm font-medium">Emitidos</h2>
        <DataTable
          columns={documentColumns}
          data={documentsResponse?.items ?? []}
          isLoading={isLoading}
          emptyMessage="Nenhum documento emitido."
        />
      </div>

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Novo documento</DialogTitle>
          </DialogHeader>
          <div className="flex flex-col gap-4">
            <Select value={newDocumentType} onValueChange={setNewDocumentType}>
              <SelectTrigger aria-label="Tipo de documento">
                <SelectValue placeholder="Escolha o tipo de documento" />
              </SelectTrigger>
              <SelectContent>
                {creatableTypes.map((type) => (
                  <SelectItem key={type.code} value={type.code ?? ''}>
                    {type.code} — {type.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button onClick={handleCreate} disabled={'' === newDocumentType || createDraft.isPending}>
              {createDraft.isPending ? 'A criar…' : 'Criar rascunho'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}
