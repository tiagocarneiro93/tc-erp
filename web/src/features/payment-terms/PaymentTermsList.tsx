import type { ColumnDef } from '@tanstack/react-table'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import {
  getGetPaymentTermsListQueryOptions,
  useDeletePaymentTermsDeactivate,
  useGetPaymentTermsList,
  type GetPaymentTermsList200ItemsItem,
} from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DataTable } from '@/components/data-table/DataTable'
import { PaymentTermsForm } from '@/features/payment-terms/PaymentTermsForm'

type PaymentTerms = GetPaymentTermsList200ItemsItem

export function PaymentTermsList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetPaymentTermsList(companyId)
  const deactivate = useDeletePaymentTermsDeactivate()
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<PaymentTerms | null>(null)

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetPaymentTermsListQueryOptions(companyId).queryKey })

  const columns: ColumnDef<PaymentTerms>[] = [
    { accessorKey: 'name', header: 'Nome' },
    { accessorKey: 'days', header: 'Dias' },
    {
      id: 'is_default',
      header: 'Por omissão',
      cell: ({ row }) => (row.original.is_default ? <Badge>Por omissão</Badge> : null),
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
                  deactivate.mutate({ companyId, paymentTermsId: row.original.id }, { onSuccess: invalidate })
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
        <Button onClick={() => setCreating(true)}>Novo prazo de pagamento</Button>
      </div>

      <DataTable columns={columns} data={data?.items ?? []} isLoading={isLoading} emptyMessage="Nenhum prazo de pagamento encontrado." />

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Novo prazo de pagamento</DialogTitle>
          </DialogHeader>
          <PaymentTermsForm
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
            <DialogTitle>Editar prazo de pagamento</DialogTitle>
          </DialogHeader>
          {editing?.id && (
            <PaymentTermsForm
              companyId={companyId}
              paymentTerms={{ ...editing, id: editing.id }}
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
