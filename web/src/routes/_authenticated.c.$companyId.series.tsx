import { createFileRoute } from '@tanstack/react-router'

import { SeriesList } from '@/features/series/SeriesList'

export const Route = createFileRoute('/_authenticated/c/$companyId/series')({
  component: SeriesPage,
})

function SeriesPage() {
  const { companyId } = Route.useParams()

  return <SeriesList companyId={companyId} />
}
