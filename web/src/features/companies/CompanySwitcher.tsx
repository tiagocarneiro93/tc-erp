import { Link, useParams } from '@tanstack/react-router'
import { ChevronsUpDownIcon } from 'lucide-react'

import { useGetCompaniesListMine } from '@/api/generated'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

export function CompanySwitcher() {
  const { companyId } = useParams({ from: '/_authenticated/c/$companyId' })
  const { data } = useGetCompaniesListMine()
  const companies = data?.items ?? []
  const current = companies.find((company) => company.id === companyId)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" className="w-56 justify-between">
          <span className="truncate">{current?.legal_name ?? 'Selecionar empresa'}</span>
          <ChevronsUpDownIcon className="opacity-50" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-56">
        {companies.map((company) => (
          <DropdownMenuItem key={company.id} asChild>
            <Link to="/c/$companyId" params={{ companyId: company.id ?? '' }}>
              {company.legal_name}
            </Link>
          </DropdownMenuItem>
        ))}
        <DropdownMenuItem asChild>
          <Link to="/companies">Ver todas as empresas</Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
