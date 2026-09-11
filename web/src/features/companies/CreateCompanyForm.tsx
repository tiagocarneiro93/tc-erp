import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { usePostCompaniesCreate } from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const schema = z.object({
  nif: z.string().min(1, 'Introduza o NIF'),
  legal_name: z.string().min(1, 'Introduza a firma'),
})

type FormValues = z.infer<typeof schema>

interface CreateCompanyFormProps {
  onSuccess: () => void
}

export function CreateCompanyForm({ onSuccess }: CreateCompanyFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const createCompany = usePostCompaniesCreate<ApiError>()

  const onSubmit = handleSubmit((values) => {
    createCompany.mutate({ data: values }, { onSuccess })
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
      <div className="flex flex-col gap-2">
        <Label htmlFor="nif">NIF</Label>
        <Input id="nif" {...register('nif')} />
        {errors.nif && <p className="text-destructive text-sm">{errors.nif.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="legal_name">Firma</Label>
        <Input id="legal_name" {...register('legal_name')} />
        {errors.legal_name && <p className="text-destructive text-sm">{errors.legal_name.message}</p>}
      </div>

      {createCompany.isError && (
        <p className="text-destructive text-sm" role="alert">
          {409 === createCompany.error.status
            ? 'Já existe uma empresa com este NIF.'
            : 422 === createCompany.error.status
              ? 'NIF inválido.'
              : 'Ocorreu um erro. Tente novamente.'}
        </p>
      )}

      <Button type="submit" disabled={createCompany.isPending}>
        {createCompany.isPending ? 'A criar…' : 'Criar empresa'}
      </Button>
    </form>
  )
}
