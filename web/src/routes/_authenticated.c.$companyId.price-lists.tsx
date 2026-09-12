import { createFileRoute } from '@tanstack/react-router'

import { PriceListsList } from '@/features/price-lists/PriceListsList'

export const Route = createFileRoute('/_authenticated/c/$companyId/price-lists')({
  component: PriceListsPage,
})

function PriceListsPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Tabelas de preços</h1>
      <PriceListsList companyId={companyId} />
    </div>
  )
}
