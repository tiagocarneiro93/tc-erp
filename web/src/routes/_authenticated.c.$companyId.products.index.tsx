import { createFileRoute } from '@tanstack/react-router'

import { ProductsList } from '@/features/products/ProductsList'

export const Route = createFileRoute('/_authenticated/c/$companyId/products/')({
  component: ProductsPage,
})

function ProductsPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Produtos</h1>
      <ProductsList companyId={companyId} />
    </div>
  )
}
