import { createFileRoute, Link } from '@tanstack/react-router'

import { useGetProductsGet } from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { ProductComponentsSection } from '@/features/products/ProductComponentsSection'
import { ProductPricesSection } from '@/features/products/ProductPricesSection'

export const Route = createFileRoute('/_authenticated/c/$companyId/products/$productId')({
  component: ProductDetailPage,
})

function ProductDetailPage() {
  const { companyId, productId } = Route.useParams()
  const { data: product, isLoading } = useGetProductsGet(companyId, productId)

  if (isLoading || !product) {
    return <p className="text-muted-foreground text-sm">A carregar…</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link to="/c/$companyId/products" params={{ companyId }} className="text-muted-foreground text-sm hover:underline">
          ← Produtos
        </Link>
        <div className="mt-2 flex items-center gap-3">
          <h1 className="text-xl font-semibold">{product.description}</h1>
          {'kit' === product.kind && <Badge>Kit</Badge>}
        </div>
        <p className="text-muted-foreground text-sm">{product.code}</p>
      </div>

      <ProductPricesSection companyId={companyId} productId={productId} />

      {'kit' === product.kind && <ProductComponentsSection companyId={companyId} kitProductId={productId} />}
    </div>
  )
}
