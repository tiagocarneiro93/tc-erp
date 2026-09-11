import { Link } from '@tanstack/react-router'

import { useGetCompaniesListMine } from '@/api/generated'

export function CompanyList() {
  const { data, isLoading } = useGetCompaniesListMine()

  if (isLoading) {
    return <p className="text-muted-foreground text-sm">A carregar…</p>
  }

  const companies = data?.items ?? []

  if (0 === companies.length) {
    return <p className="text-muted-foreground text-sm">Ainda não pertence a nenhuma empresa.</p>
  }

  return (
    <ul className="flex flex-col gap-2">
      {companies.map((company) => (
        <li key={company.id}>
          <Link
            to="/c/$companyId"
            params={{ companyId: company.id ?? '' }}
            className="hover:bg-accent flex items-center justify-between rounded-md border p-3"
          >
            <span className="font-medium">{company.legal_name}</span>
            <span className="text-muted-foreground text-sm">{company.role}</span>
          </Link>
        </li>
      ))}
    </ul>
  )
}
