import { useQueryClient } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'

import { getGetCompanyUsersListQueryOptions } from '@/api/generated'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { InviteMemberForm } from '@/features/members/InviteMemberForm'
import { MembersList } from '@/features/members/MembersList'

export const Route = createFileRoute('/_authenticated/c/$companyId/members')({
  component: MembersPage,
})

function MembersPage() {
  const { companyId } = Route.useParams()
  const [inviting, setInviting] = useState(false)
  const queryClient = useQueryClient()

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Membros</h1>
        <Button variant={inviting ? 'outline' : 'default'} onClick={() => setInviting((value) => !value)}>
          {inviting ? 'Cancelar' : 'Convidar membro'}
        </Button>
      </div>

      {inviting && (
        <Card>
          <CardHeader>
            <CardTitle>Convidar membro</CardTitle>
          </CardHeader>
          <CardContent>
            <InviteMemberForm
              companyId={companyId}
              onSuccess={async () => {
                await queryClient.invalidateQueries({
                  queryKey: getGetCompanyUsersListQueryOptions(companyId).queryKey,
                })
                setInviting(false)
              }}
            />
          </CardContent>
        </Card>
      )}

      <MembersList companyId={companyId} />
    </div>
  )
}
