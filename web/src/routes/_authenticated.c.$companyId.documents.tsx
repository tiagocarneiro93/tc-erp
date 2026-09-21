import { createFileRoute, Outlet } from '@tanstack/react-router'

/**
 * Layout-only, same split as `products.tsx`: `documents.index.tsx` (the
 * list), `documents.drafts.$draftId.tsx` (the editor) and
 * `documents.$documentId.tsx` (the issued-document detail) render into
 * this `<Outlet />`.
 */
export const Route = createFileRoute('/_authenticated/c/$companyId/documents')({
  component: () => <Outlet />,
})
