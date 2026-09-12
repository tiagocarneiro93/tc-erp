import { createFileRoute } from '@tanstack/react-router'

import { ProductFamiliesList } from '@/features/product-families/ProductFamiliesList'

export const Route = createFileRoute('/_authenticated/c/$companyId/product-families')({
  component: ProductFamiliesPage,
})

function ProductFamiliesPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Famílias de produtos</h1>
      <ProductFamiliesList companyId={companyId} />
    </div>
  )
}
