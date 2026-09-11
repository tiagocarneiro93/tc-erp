import { useQueryClient } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'

import { getGetCompaniesListMineQueryOptions, getGetMeQueryOptions } from '@/api/generated'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { CompanyList } from '@/features/companies/CompanyList'
import { CreateCompanyForm } from '@/features/companies/CreateCompanyForm'

export const Route = createFileRoute('/_authenticated/companies')({
  component: CompaniesPage,
})

function CompaniesPage() {
  const [creating, setCreating] = useState(false)
  const queryClient = useQueryClient()

  return (
    <div className="mx-auto flex min-h-svh max-w-2xl flex-col gap-6 p-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">As minhas empresas</h1>
        <Button variant={creating ? 'outline' : 'default'} onClick={() => setCreating((value) => !value)}>
          {creating ? 'Cancelar' : 'Nova empresa'}
        </Button>
      </div>

      {creating && (
        <Card>
          <CardHeader>
            <CardTitle>Criar empresa</CardTitle>
          </CardHeader>
          <CardContent>
            <CreateCompanyForm
              onSuccess={async () => {
                // /_authenticated/c/$companyId's beforeLoad guards on the
                // cached /me companies list, not /companies -- without this,
                // ensureQueryData returns the pre-creation list (it doesn't
                // revalidate stale cache by default) and clicking straight
                // into the new company bounces back here.
                await Promise.all([
                  queryClient.invalidateQueries({ queryKey: getGetCompaniesListMineQueryOptions().queryKey }),
                  queryClient.invalidateQueries({ queryKey: getGetMeQueryOptions().queryKey }),
                ])
                setCreating(false)
              }}
            />
          </CardContent>
        </Card>
      )}

      <CompanyList />
    </div>
  )
}
