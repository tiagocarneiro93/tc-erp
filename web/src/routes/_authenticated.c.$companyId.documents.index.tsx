import { createFileRoute } from '@tanstack/react-router'

import { DocumentsList } from '@/features/documents/DocumentsList'

export const Route = createFileRoute('/_authenticated/c/$companyId/documents/')({
  component: DocumentsPage,
})

function DocumentsPage() {
  const { companyId } = Route.useParams()

  return <DocumentsList companyId={companyId} />
}
