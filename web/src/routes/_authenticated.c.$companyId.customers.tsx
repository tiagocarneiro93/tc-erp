import { createFileRoute } from '@tanstack/react-router'

import { CustomersList } from '@/features/customers/CustomersList'

export const Route = createFileRoute('/_authenticated/c/$companyId/customers')({
  component: CustomersPage,
})

function CustomersPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Clientes</h1>
      <CustomersList companyId={companyId} />
    </div>
  )
}
