import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetSuppliersListQueryOptions,
  useDeleteSuppliersDeactivate,
  useGetSuppliersList,
  type GetSuppliersList200ItemsItem,
} from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { DataTable } from '@/components/data-table/DataTable'
import { SupplierForm } from '@/features/suppliers/SupplierForm'
import { useCursorPagination } from '@/lib/use-cursor-pagination'

type Supplier = GetSuppliersList200ItemsItem

export function SuppliersList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const pagination = useCursorPagination()
  const { search, cursor } = pagination
  const { data, isLoading } = useGetSuppliersList(companyId, { search: search || undefined, cursor, limit: 20 })
  const deactivate = useDeleteSuppliersDeactivate()
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Supplier | null>(null)

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetSuppliersListQueryOptions(companyId).queryKey })

  const columns: ColumnDef<Supplier>[] = [
    { accessorKey: 'code', header: 'Código' },
    { accessorKey: 'nif', header: 'NIF' },
    { accessorKey: 'name', header: 'Nome' },
    { accessorKey: 'city', header: 'Localidade', cell: ({ row }) => row.original.city ?? '—' },
    {
      id: 'status',
      header: 'Estado',
      cell: ({ row }) =>
        row.original.active ? <Badge variant="outline">Ativo</Badge> : <Badge variant="secondary">Inativo</Badge>,
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) => (
        <div className="flex justify-end gap-2">
          <Button variant="ghost" size="sm" onClick={() => setEditing(row.original)}>
            Editar
          </Button>
          {row.original.active && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                if (row.original.id) {
                  deactivate.mutate({ companyId, supplierId: row.original.id }, { onSuccess: invalidate })
                }
              }}
            >
              Desativar
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-4">
        <Input
          placeholder="Pesquisar por código, NIF ou nome…"
          value={search}
          onChange={(event) => pagination.setSearch(event.target.value)}
          className="max-w-sm"
        />
        <Button onClick={() => setCreating(true)}>Novo fornecedor</Button>
      </div>

      <DataTable columns={columns} data={data?.items ?? []} isLoading={isLoading} emptyMessage="Nenhum fornecedor encontrado." />

      <div className="flex justify-end gap-2">
        <Button variant="outline" size="sm" disabled={!pagination.hasPrev} onClick={pagination.goPrev}>
          Anterior
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={!data?.next_cursor}
          onClick={() => data?.next_cursor && pagination.goNext(data.next_cursor)}
        >
          Seguinte
        </Button>
      </div>

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Novo fornecedor</DialogTitle>
          </DialogHeader>
          <SupplierForm
            companyId={companyId}
            onSuccess={() => {
              void invalidate()
              setCreating(false)
            }}
          />
        </DialogContent>
      </Dialog>

      <Dialog open={null !== editing} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Editar fornecedor</DialogTitle>
          </DialogHeader>
          {editing?.id && (
            <SupplierForm
              companyId={companyId}
              supplier={{ ...editing, id: editing.id }}
              onSuccess={() => {
                void invalidate()
                setEditing(null)
              }}
            />
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
