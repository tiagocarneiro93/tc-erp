import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetProductFamiliesListQueryOptions,
  useGetProductFamiliesList,
  type GetProductFamiliesList200ItemsItem,
} from '@/api/generated'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DataTable } from '@/components/data-table/DataTable'
import { ProductFamilyForm } from '@/features/product-families/ProductFamilyForm'

type Family = GetProductFamiliesList200ItemsItem

export function ProductFamiliesList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetProductFamiliesList(companyId)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Family | null>(null)

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetProductFamiliesListQueryOptions(companyId).queryKey })

  const familiesById = new Map((data?.items ?? []).map((family) => [family.id, family]))

  const columns: ColumnDef<Family>[] = [
    { accessorKey: 'name', header: 'Nome' },
    {
      id: 'parent',
      header: 'Família-mãe',
      cell: ({ row }) => familiesById.get(row.original.parent_id ?? undefined)?.name ?? '—',
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) => (
        <div className="flex justify-end">
          <Button variant="ghost" size="sm" onClick={() => setEditing(row.original)}>
            Editar
          </Button>
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <Button onClick={() => setCreating(true)}>Nova família</Button>
      </div>

      <DataTable columns={columns} data={data?.items ?? []} isLoading={isLoading} emptyMessage="Nenhuma família encontrada." />

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Nova família</DialogTitle>
          </DialogHeader>
          <ProductFamilyForm
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
            <DialogTitle>Editar família</DialogTitle>
          </DialogHeader>
          {editing?.id && (
            <ProductFamilyForm
              companyId={companyId}
              family={{ ...editing, id: editing.id }}
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
