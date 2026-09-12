import { createFileRoute, Outlet } from '@tanstack/react-router'

/**
 * Layout-only: `products.index.tsx` (the list) and `products.$productId.tsx`
 * (the detail, with its price/kit-composition editors) render into this
 * `<Outlet />` — same parent+index+child split as `_authenticated.c.$companyId`
 * itself. Without this Outlet, TanStack Router matches the child route (the
 * URL updates) but has nowhere to render its component, leaving whatever
 * this route last rendered on screen.
 */
export const Route = createFileRoute('/_authenticated/c/$companyId/products')({
  component: () => <Outlet />,
})
