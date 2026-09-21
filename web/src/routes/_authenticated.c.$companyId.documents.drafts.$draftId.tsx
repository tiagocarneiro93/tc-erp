import { createFileRoute } from '@tanstack/react-router'

import { DraftEditor } from '@/features/documents/DraftEditor'

export const Route = createFileRoute('/_authenticated/c/$companyId/documents/drafts/$draftId')({
  component: DraftEditorPage,
})

function DraftEditorPage() {
  const { companyId, draftId } = Route.useParams()

  return <DraftEditor companyId={companyId} draftId={draftId} />
}
