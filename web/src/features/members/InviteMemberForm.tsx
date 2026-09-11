import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostCompanyUsersInvite } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ROLES } from '@/features/members/roles'

const schema = z.object({
  email: z.string().min(1, 'Introduza o email').email('Email inválido'),
  name: z.string().min(1, 'Introduza o nome'),
  role: z.string().min(1, 'Escolha um cargo'),
})

type FormValues = z.infer<typeof schema>

interface InviteMemberFormProps {
  companyId: string
  onSuccess: () => void
}

export function InviteMemberForm({ companyId, onSuccess }: InviteMemberFormProps) {
  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { role: 'read_only' } })

  const invite = usePostCompanyUsersInvite<ApiError>()

  const onSubmit = handleSubmit((values) => {
    invite.mutate({ companyId, data: values }, { onSuccess })
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="invite-email">Email</Label>
        <Input id="invite-email" type="email" {...register('email')} />
        {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="invite-name">Nome</Label>
        <Input id="invite-name" {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="invite-role">Cargo</Label>
        <Controller
          control={control}
          name="role"
          render={({ field }) => (
            <Select value={field.value} onValueChange={field.onChange}>
              <SelectTrigger id="invite-role">
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
          )}
        />
      </div>

      {invite.isError && (
        <p className="text-destructive text-sm" role="alert">
          {409 === invite.error.status ? 'Esta pessoa já é membro desta empresa.' : 'Ocorreu um erro. Tente novamente.'}
        </p>
      )}

      <Button type="submit" disabled={invite.isPending}>
        {invite.isPending ? 'A convidar…' : 'Convidar'}
      </Button>
    </form>
  )
}
