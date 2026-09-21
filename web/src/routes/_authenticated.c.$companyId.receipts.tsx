import { createFileRoute, Outlet } from '@tanstack/react-router'

export const Route = createFileRoute('/_authenticated/c/$companyId/receipts')({
  component: () => <Outlet />,
})
