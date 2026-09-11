import { createFileRoute } from '@tanstack/react-router'

export const Route = createFileRoute('/_authenticated/c/$companyId/')({
  component: CompanyDashboard,
})

function CompanyDashboard() {
  return (
    <div>
      <h1 className="text-xl font-semibold">Painel</h1>
      <p className="text-muted-foreground mt-2 text-sm">Em breve.</p>
    </div>
  )
}
