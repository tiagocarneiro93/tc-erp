import { createFileRoute } from '@tanstack/react-router'

import { WarehousesList } from '@/features/warehouses/WarehousesList'

export const Route = createFileRoute('/_authenticated/c/$companyId/warehouses')({
  component: WarehousesPage,
})

function WarehousesPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Armazéns</h1>
      <WarehousesList companyId={companyId} />
    </div>
  )
}
