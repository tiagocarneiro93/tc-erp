import { Link } from '@tanstack/react-router'
import {
  BookOpenIcon,
  BoxesIcon,
  CalendarClockIcon,
  ContactIcon,
  FolderTreeIcon,
  LayoutDashboardIcon,
  SettingsIcon,
  TagsIcon,
  TruckIcon,
  UsersIcon,
  WarehouseIcon,
} from 'lucide-react'

const NAV_ITEMS = [
  { to: '/c/$companyId', label: 'Painel', icon: LayoutDashboardIcon, exact: true },
  { to: '/c/$companyId/customers', label: 'Clientes', icon: ContactIcon, exact: false },
  { to: '/c/$companyId/suppliers', label: 'Fornecedores', icon: TruckIcon, exact: false },
  { to: '/c/$companyId/products', label: 'Produtos', icon: BoxesIcon, exact: false },
  { to: '/c/$companyId/product-families', label: 'Famílias', icon: FolderTreeIcon, exact: false },
  { to: '/c/$companyId/price-lists', label: 'Tabelas de preços', icon: TagsIcon, exact: false },
  { to: '/c/$companyId/warehouses', label: 'Armazéns', icon: WarehouseIcon, exact: false },
  { to: '/c/$companyId/payment-terms', label: 'Prazos de pagamento', icon: CalendarClockIcon, exact: false },
  { to: '/c/$companyId/reference-data', label: 'Dados de referência', icon: BookOpenIcon, exact: false },
  { to: '/c/$companyId/members', label: 'Membros', icon: UsersIcon, exact: false },
  { to: '/c/$companyId/settings', label: 'Definições', icon: SettingsIcon, exact: false },
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
