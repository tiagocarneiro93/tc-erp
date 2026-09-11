import { Link } from '@tanstack/react-router'
import { LayoutDashboardIcon, UsersIcon } from 'lucide-react'

const NAV_ITEMS = [
  { to: '/c/$companyId', label: 'Painel', icon: LayoutDashboardIcon, exact: true },
  { to: '/c/$companyId/members', label: 'Membros', icon: UsersIcon, exact: false },
] as const

export function AppSidebar({ companyId }: { companyId: string }) {
  return (
    <aside className="w-56 shrink-0 border-r p-4">
      <nav className="flex flex-col gap-1">
        {NAV_ITEMS.map((item) => (
          <Link
            key={item.to}
            to={item.to}
            params={{ companyId }}
            activeOptions={{ exact: item.exact }}
            activeProps={{ className: 'bg-accent text-accent-foreground' }}
            className="flex items-center gap-2 rounded-md px-3 py-2 text-sm"
          >
            <item.icon className="size-4" />
            {item.label}
          </Link>
        ))}
      </nav>
    </aside>
  )
}
