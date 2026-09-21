import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { getGetReceiptsListQueryOptions, useGetReceiptsList, type GetReceiptsList200ItemsItem } from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DataTable } from '@/components/data-table/DataTable'
import { ReceiptForm } from '@/features/receipts/ReceiptForm'
import { formatMoney } from '@/lib/format/decimal'

const STATUS_LABELS: Record<string, string> = { N: 'Normal', A: 'Anulado' }

export function ReceiptsList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetReceiptsList(companyId, {})
  const [creating, setCreating] = useState(false)

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetReceiptsListQueryOptions(companyId, {}).queryKey })

  const columns: ColumnDef<GetReceiptsList200ItemsItem>[] = [
    { accessorKey: 'document_no', header: 'N.º' },
    { accessorKey: 'customer_name', header: 'Cliente' },
    { id: 'total', header: 'Total', cell: ({ row }) => formatMoney(row.original.total ?? '0') },
    { accessorKey: 'payment_method', header: 'Forma de pagamento' },
    {
      id: 'status',
      header: 'Estado',
      cell: ({ row }) => (
        <Badge variant={'N' === row.original.status ? 'outline' : 'secondary'}>
          {STATUS_LABELS[row.original.status ?? ''] ?? row.original.status}
        </Badge>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Recibos</h1>
        <Button onClick={() => setCreating(true)}>Novo recibo</Button>
      </div>

      <DataTable
        columns={columns}
        data={data?.items ?? []}
        isLoading={isLoading}
        emptyMessage="Nenhum recibo emitido."
      />

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Novo recibo</DialogTitle>
          </DialogHeader>
          <ReceiptForm
            companyId={companyId}
            onSuccess={() => {
              void invalidate()
              setCreating(false)
            }}
          />
        </DialogContent>
      </Dialog>
    </div>
  )
}
