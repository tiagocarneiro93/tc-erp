import { createFileRoute } from '@tanstack/react-router'

import { SuppliersList } from '@/features/suppliers/SuppliersList'

export const Route = createFileRoute('/_authenticated/c/$companyId/suppliers')({
  component: SuppliersPage,
})

function SuppliersPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Fornecedores</h1>
      <SuppliersList companyId={companyId} />
    </div>
  )
}
