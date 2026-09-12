import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { Link } from '@tanstack/react-router'
import { useState } from 'react'

import {
  getGetProductsListQueryOptions,
  useDeleteProductsDeactivate,
  useGetProductsList,
  type GetProductsList200ItemsItem,
} from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { DataTable } from '@/components/data-table/DataTable'
import { ProductForm } from '@/features/products/ProductForm'
import { useCursorPagination } from '@/lib/use-cursor-pagination'

type Product = GetProductsList200ItemsItem

export function ProductsList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const pagination = useCursorPagination()
  const { search, cursor } = pagination
  const { data, isLoading } = useGetProductsList(companyId, { search: search || undefined, cursor, limit: 20 })
  const deactivate = useDeleteProductsDeactivate()
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Product | null>(null)

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetProductsListQueryOptions(companyId).queryKey })

  const columns: ColumnDef<Product>[] = [
    {
      accessorKey: 'code',
      header: 'Código',
      cell: ({ row }) => (
        <Link
          to="/c/$companyId/products/$productId"
          params={{ companyId, productId: row.original.id ?? '' }}
          className="hover:underline"
        >
          {row.original.code}
        </Link>
      ),
    },
    { accessorKey: 'description', header: 'Descrição' },
    {
      id: 'kind',
      header: 'Género',
      cell: ({ row }) => (row.original.kind === 'kit' ? <Badge>Kit</Badge> : 'Simples'),
    },
    { accessorKey: 'unit_code', header: 'Unidade' },
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
                  deactivate.mutate({ companyId, productId: row.original.id }, { onSuccess: invalidate })
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
          placeholder="Pesquisar por código ou descrição…"
          value={search}
          onChange={(event) => pagination.setSearch(event.target.value)}
          className="max-w-sm"
        />
        <Button onClick={() => setCreating(true)}>Novo produto</Button>
      </div>

      <DataTable columns={columns} data={data?.items ?? []} isLoading={isLoading} emptyMessage="Nenhum produto encontrado." />

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
            <DialogTitle>Novo produto</DialogTitle>
          </DialogHeader>
          <ProductForm
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
            <DialogTitle>Editar produto</DialogTitle>
          </DialogHeader>
          {editing?.id && (
            <ProductForm
              companyId={companyId}
              product={{ ...editing, id: editing.id }}
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
