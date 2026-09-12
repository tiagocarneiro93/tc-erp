import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { getGetPriceListsListQueryOptions, useGetPriceListsList, type GetPriceListsList200ItemsItem } from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DataTable } from '@/components/data-table/DataTable'
import { PriceListForm } from '@/features/price-lists/PriceListForm'

type PriceList = GetPriceListsList200ItemsItem

export function PriceListsList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetPriceListsList(companyId)
  const [creating, setCreating] = useState(false)

  const columns: ColumnDef<PriceList>[] = [
    { accessorKey: 'name', header: 'Nome' },
    {
      id: 'default_includes_vat',
      header: 'Preços por omissão',
      cell: ({ row }) => (
        <Badge variant="outline">{row.original.default_includes_vat ? 'Com IVA' : 'Sem IVA'}</Badge>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <Button onClick={() => setCreating(true)}>Nova tabela de preços</Button>
      </div>

      <DataTable
        columns={columns}
        data={data?.items ?? []}
        isLoading={isLoading}
        emptyMessage="Nenhuma tabela de preços encontrada."
      />

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Nova tabela de preços</DialogTitle>
          </DialogHeader>
          <PriceListForm
            companyId={companyId}
            onSuccess={() => {
              void queryClient.invalidateQueries({ queryKey: getGetPriceListsListQueryOptions(companyId).queryKey })
              setCreating(false)
            }}
          />
        </DialogContent>
      </Dialog>
    </div>
  )
}
