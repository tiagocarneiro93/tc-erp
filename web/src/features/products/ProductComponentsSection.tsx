import { useQueryClient } from '@tanstack/react-query'
import { Controller, useForm } from 'react-hook-form'

import {
  getGetProductComponentsListQueryOptions,
  useDeleteProductComponentsRemove,
  useGetProductComponentsList,
  useGetProductsList,
  usePutProductComponentsSet,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiErrorMessage } from '@/lib/api-error'
import { formatQuantity, formatMoney } from '@/lib/format/decimal'

interface FormValues {
  component_product_id: string
  quantity: string
  sort_order: string
}

export function ProductComponentsSection({ companyId, kitProductId }: { companyId: string; kitProductId: string }) {
  const queryClient = useQueryClient()
  const { data: components } = useGetProductComponentsList(companyId, kitProductId)
  const { data: candidateProducts } = useGetProductsList(companyId, { active: true, limit: 100 })
  const productsById = new Map((candidateProducts?.items ?? []).map((candidate) => [candidate.id, candidate]))

  const eligibleComponents = (candidateProducts?.items ?? []).filter(
    (candidate) => candidate.id !== kitProductId && 'kit' !== candidate.kind,
  )

  const { register, control, handleSubmit, reset } = useForm<FormValues>({
    defaultValues: { component_product_id: '', quantity: '', sort_order: '0' },
  })

  const setComponent = usePutProductComponentsSet<ApiError>()
  const removeComponent = useDeleteProductComponentsRemove<ApiError>()

  const invalidate = () =>
    queryClient.invalidateQueries({ queryKey: getGetProductComponentsListQueryOptions(companyId, kitProductId).queryKey })

  const onSubmit = handleSubmit((values) => {
    setComponent.mutate(
      {
        companyId,
        kitProductId,
        componentProductId: values.component_product_id,
        data: { quantity: values.quantity, sort_order: Number(values.sort_order) },
      },
      {
        onSuccess: () => {
          void invalidate()
          reset({ component_product_id: '', quantity: '', sort_order: '0' })
        },
      },
    )
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle>Composição do kit</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Componente</TableHead>
              <TableHead>Quantidade</TableHead>
              <TableHead>Ordem</TableHead>
              <TableHead>IVA</TableHead>
              <TableHead />
            </TableRow>
          </TableHeader>
          <TableBody>
            {0 === (components?.items?.length ?? 0) ? (
              <TableRow>
                <TableCell colSpan={5} className="text-muted-foreground py-6 text-center">
                  Este kit ainda não tem componentes.
                </TableCell>
              </TableRow>
            ) : (
              components?.items?.map((component) => (
                <TableRow key={component.component_product_id}>
                  <TableCell>
                    {productsById.get(component.component_product_id)?.description ?? component.component_product_id}
                  </TableCell>
                  <TableCell>{formatQuantity(component.quantity ?? '0')}</TableCell>
                  <TableCell>{component.sort_order}</TableCell>
                  <TableCell>
                    {component.vat_rate_mismatch && <Badge variant="secondary">Taxa diferente</Badge>}
                  </TableCell>
                  <TableCell>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        if (component.component_product_id) {
                          removeComponent.mutate(
                            { companyId, kitProductId, componentProductId: component.component_product_id },
                            { onSuccess: invalidate },
                          )
                        }
                      }}
                    >
                      Remover
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>

        {components?.estimated_cost && (
          <p className="text-muted-foreground text-sm">Custo estimado: {formatMoney(components.estimated_cost)}</p>
        )}

        <form onSubmit={onSubmit} className="flex flex-col gap-4 border-t pt-4" noValidate>
          <div className="grid grid-cols-3 items-end gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="component-product">Produto</Label>
              <Controller
                control={control}
                name="component_product_id"
                rules={{ required: true }}
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger id="component-product">
                      <SelectValue placeholder="Escolha um produto" />
                    </SelectTrigger>
                    <SelectContent>
                      {eligibleComponents.map((candidate) => (
                        <SelectItem key={candidate.id} value={candidate.id ?? ''}>
                          {candidate.code} — {candidate.description}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="component-quantity">Quantidade</Label>
              <Input id="component-quantity" inputMode="decimal" {...register('quantity', { required: true })} />
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="component-sort-order">Ordem</Label>
              <Input id="component-sort-order" type="number" {...register('sort_order')} />
            </div>
          </div>

          {setComponent.isError && (
            <p className="text-destructive text-sm" role="alert">
              {apiErrorMessage(setComponent.error, {
                422: 'Um kit não pode conter outro kit, ou a quantidade é inválida.',
                403: 'Sem permissão para gerir produtos.',
                404: 'Produto não encontrado.',
              })}
            </p>
          )}

          <Button type="submit" disabled={setComponent.isPending} className="w-fit">
            {setComponent.isPending ? 'A guardar…' : 'Adicionar componente'}
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
