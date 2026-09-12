import { createFileRoute } from '@tanstack/react-router'

import { PaymentTermsList } from '@/features/payment-terms/PaymentTermsList'

export const Route = createFileRoute('/_authenticated/c/$companyId/payment-terms')({
  component: PaymentTermsPage,
})

function PaymentTermsPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Prazos de pagamento</h1>
      <PaymentTermsList companyId={companyId} />
    </div>
  )
}
