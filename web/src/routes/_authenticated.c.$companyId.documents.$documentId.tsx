import { createFileRoute } from '@tanstack/react-router'

import { DocumentDetail } from '@/features/documents/DocumentDetail'

export const Route = createFileRoute('/_authenticated/c/$companyId/documents/$documentId')({
  component: DocumentDetailPage,
})

function DocumentDetailPage() {
  const { companyId, documentId } = Route.useParams()

  return <DocumentDetail companyId={companyId} documentId={documentId} />
}
