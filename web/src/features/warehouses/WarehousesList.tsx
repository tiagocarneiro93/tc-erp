import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetWarehousesListQueryOptions,
  useDeleteWarehousesDeactivate,
  useGetWarehousesList,
  type GetWarehousesList200ItemsItem,
} from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DataTable } from '@/components/data-table/DataTable'
import { WarehouseForm } from '@/features/warehouses/WarehouseForm'

type Warehouse = GetWarehousesList200ItemsItem

export function WarehousesList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetWarehousesList(companyId)
  const deactivate = useDeleteWarehousesDeactivate()
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Warehouse | null>(null)

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetWarehousesListQueryOptions(companyId).queryKey })

  const columns: ColumnDef<Warehouse>[] = [
    { accessorKey: 'code', header: 'Código' },
    { accessorKey: 'name', header: 'Nome' },
    { accessorKey: 'address', header: 'Morada', cell: ({ row }) => row.original.address ?? '—' },
    {
      id: 'is_default',
      header: 'Principal',
      cell: ({ row }) => (row.original.is_default ? <Badge>Principal</Badge> : null),
    },
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
                  deactivate.mutate({ companyId, warehouseId: row.original.id }, { onSuccess: invalidate })
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
      <div className="flex items-center justify-between">
        <Button onClick={() => setCreating(true)}>Novo armazém</Button>
      </div>

      <DataTable columns={columns} data={data?.items ?? []} isLoading={isLoading} emptyMessage="Nenhum armazém encontrado." />

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Novo armazém</DialogTitle>
          </DialogHeader>
          <WarehouseForm
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
            <DialogTitle>Editar armazém</DialogTitle>
          </DialogHeader>
          {editing?.id && (
            <WarehouseForm
              companyId={companyId}
              warehouse={{ ...editing, id: editing.id }}
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
