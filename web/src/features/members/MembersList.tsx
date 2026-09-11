import { useQueryClient } from '@tanstack/react-query'

import {
  getGetCompanyUsersListQueryOptions,
  useDeleteCompanyUsersRemove,
  useGetCompanyUsersList,
  usePutCompanyUsersChangeRole,
} from '@/api/generated'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ROLES } from '@/features/members/roles'

export function MembersList({ companyId }: { companyId: string }) {
  const queryClient = useQueryClient()
  const { data, isLoading } = useGetCompanyUsersList(companyId)
  const changeRole = usePutCompanyUsersChangeRole()
  const remove = useDeleteCompanyUsersRemove()

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetCompanyUsersListQueryOptions(companyId).queryKey })

  if (isLoading) {
    return <p className="text-muted-foreground text-sm">A carregar…</p>
  }

  const members = data?.items ?? []

  return (
    <table className="w-full text-sm">
      <thead>
        <tr className="border-b text-left">
          <th className="py-2 font-medium">Nome</th>
          <th className="py-2 font-medium">Email</th>
          <th className="py-2 font-medium">Cargo</th>
          <th className="py-2" />
        </tr>
      </thead>
      <tbody>
        {members.map((member) => (
          <tr key={member.user_id} className="border-b last:border-0">
            <td className="py-2">{member.name}</td>
            <td className="py-2">{member.email}</td>
            <td className="py-2">
              <Select
                value={member.role}
                onValueChange={(role) => {
                  if (member.user_id) {
                    changeRole.mutate({ companyId, userId: member.user_id, data: { role } }, { onSuccess: invalidate })
                  }
                }}
              >
                <SelectTrigger size="sm" className="w-40">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {ROLES.map((role) => (
                    <SelectItem key={role.code} value={role.code}>
                      {role.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </td>
            <td className="py-2 text-right">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  if (member.user_id) {
                    remove.mutate({ companyId, userId: member.user_id }, { onSuccess: invalidate })
                  }
                }}
              >
                Remover
              </Button>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
