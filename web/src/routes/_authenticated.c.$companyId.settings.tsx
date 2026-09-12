import { createFileRoute } from '@tanstack/react-router'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { AtCredentialsForm } from '@/features/company-profile/AtCredentialsForm'
import { CompanyProfileForm } from '@/features/company-profile/CompanyProfileForm'

export const Route = createFileRoute('/_authenticated/c/$companyId/settings')({
  component: SettingsPage,
})

function SettingsPage() {
  const { companyId } = Route.useParams()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Definições</h1>

      <Card>
        <CardHeader>
          <CardTitle>Dados da empresa</CardTitle>
        </CardHeader>
        <CardContent>
          <CompanyProfileForm companyId={companyId} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Credenciais AT</CardTitle>
        </CardHeader>
        <CardContent>
          <AtCredentialsForm companyId={companyId} />
        </CardContent>
      </Card>
    </div>
  )
}
