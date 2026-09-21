import { createFileRoute } from '@tanstack/react-router'

import { ReceiptsList } from '@/features/receipts/ReceiptsList'

export const Route = createFileRoute('/_authenticated/c/$companyId/receipts/')({
  component: ReceiptsPage,
})

function ReceiptsPage() {
  const { companyId } = Route.useParams()

  return <ReceiptsList companyId={companyId} />
}
