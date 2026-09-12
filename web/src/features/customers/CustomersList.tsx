import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetCustomersListQueryOptions,
  useDeleteCustomersDeactivate,
  useGetCustomersList,
  type GetCustomersList200ItemsItem,
} from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { DataTable } from '@/components/data-table/DataTable'
import { CustomerForm } from '@/features/customers/CustomerForm'
import { useCursorPagination } from '@/lib/use-cursor-pagination'

type Customer = GetCustomersList200ItemsItem

export function CustomersList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const pagination = useCursorPagination()
  const { search, cursor } = pagination
  const { data, isLoading } = useGetCustomersList(companyId, { search: search || undefined, cursor, limit: 20 })
  const deactivate = useDeleteCustomersDeactivate()
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Customer | null>(null)

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetCustomersListQueryOptions(companyId).queryKey })

  const columns: ColumnDef<Customer>[] = [
    { accessorKey: 'code', header: 'Código' },
    { accessorKey: 'nif', header: 'NIF' },
    { accessorKey: 'name', header: 'Nome' },
    { accessorKey: 'city', header: 'Localidade', cell: ({ row }) => row.original.city ?? '—' },
    {
      id: 'status',
      header: 'Estado',
      cell: ({ row }) =>
        row.original.is_final_consumer ? (
          <Badge>Consumidor final</Badge>
        ) : row.original.active ? (
          <Badge variant="outline">Ativo</Badge>
        ) : (
          <Badge variant="secondary">Inativo</Badge>
        ),
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) =>
        row.original.is_final_consumer ? null : (
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
                    deactivate.mutate({ companyId, customerId: row.original.id }, { onSuccess: invalidate })
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
        <Button onClick={() => setCreating(true)}>Novo cliente</Button>
      </div>

      <DataTable columns={columns} data={data?.items ?? []} isLoading={isLoading} emptyMessage="Nenhum cliente encontrado." />

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
            <DialogTitle>Novo cliente</DialogTitle>
          </DialogHeader>
          <CustomerForm
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
            <DialogTitle>Editar cliente</DialogTitle>
          </DialogHeader>
          {editing?.id && (
            <CustomerForm
              companyId={companyId}
              customer={{ ...editing, id: editing.id }}
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
