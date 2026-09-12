import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { Controller, useForm } from 'react-hook-form'

import {
  getGetProductPricesListQueryOptions,
  useGetPriceListsList,
  useGetProductPricesList,
  usePostProductPricesCalculate,
  usePutProductPricesSet,
} from '@/api/generated'
import { ApiError } from '@/api/http-client'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { apiErrorMessage } from '@/lib/api-error'
import { formatMoney } from '@/lib/format/decimal'

interface FormValues {
  price_list_id: string
  amount: string
  includes_vat: boolean
}

export function ProductPricesSection({ companyId, productId }: { companyId: string; productId: string }) {
  const queryClient = useQueryClient()
  const { data: priceLists } = useGetPriceListsList(companyId)
  const { data: prices } = useGetProductPricesList(companyId, productId)
  const priceListsById = new Map((priceLists?.items ?? []).map((priceList) => [priceList.id, priceList]))

  const { register, control, handleSubmit, watch, reset } = useForm<FormValues>({
    defaultValues: { price_list_id: '', amount: '', includes_vat: true },
  })
  const amount = watch('amount')
  const includesVat = watch('includes_vat')
  const priceListId = watch('price_list_id')

  const calculate = usePostProductPricesCalculate<ApiError>()
  const setPrice = usePutProductPricesSet<ApiError>()

  // `calculate.mutate` isn't referentially stable across renders; a ref
  // keeps it out of the effect's dependency array so only the fields that
  // actually feed the preview (amount, includes_vat) retrigger it.
  const calculateRef = useRef(calculate.mutate)
  calculateRef.current = calculate.mutate

  useEffect(() => {
    if (!amount) {
      return
    }

    const timer = setTimeout(() => {
      calculateRef.current({ companyId, productId, data: { amount, includes_vat: includesVat } })
    }, 300)

    return () => clearTimeout(timer)
  }, [amount, includesVat, companyId, productId])

  const onSubmit = handleSubmit((values) => {
    setPrice.mutate(
      { companyId, productId, priceListId: values.price_list_id, data: { amount: values.amount, includes_vat: values.includes_vat } },
      {
        onSuccess: () => {
          void queryClient.invalidateQueries({ queryKey: getGetProductPricesListQueryOptions(companyId, productId).queryKey })
          reset({ price_list_id: values.price_list_id, amount: '', includes_vat: true })
        },
      },
    )
  })

  const hasPriceLists = 0 < (priceLists?.items?.length ?? 0)

  return (
    <Card>
      <CardHeader>
        <CardTitle>Preços</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Tabela de preços</TableHead>
              <TableHead>Valor</TableHead>
              <TableHead>IVA incluído</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {0 === (prices?.items?.length ?? 0) ? (
              <TableRow>
                <TableCell colSpan={3} className="text-muted-foreground py-6 text-center">
                  Sem preços definidos.
                </TableCell>
              </TableRow>
            ) : (
              prices?.items?.map((price) => (
                <TableRow key={price.price_list_id}>
                  <TableCell>{priceListsById.get(price.price_list_id)?.name ?? price.price_list_id}</TableCell>
                  <TableCell>{formatMoney(price.amount ?? '0')}</TableCell>
                  <TableCell>{price.includes_vat ? 'Sim' : 'Não'}</TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>

        {hasPriceLists ? (
          <form onSubmit={onSubmit} className="flex flex-col gap-4 border-t pt-4" noValidate>
            <div className="grid grid-cols-3 items-end gap-4">
              <div className="flex flex-col gap-2">
                <Label htmlFor="price-list">Tabela de preços</Label>
                <Controller
                  control={control}
                  name="price_list_id"
                  rules={{ required: true }}
                  render={({ field }) => (
                    <Select value={field.value} onValueChange={field.onChange}>
                      <SelectTrigger id="price-list">
                        <SelectValue placeholder="Escolha a tabela" />
                      </SelectTrigger>
                      <SelectContent>
                        {(priceLists?.items ?? []).map((priceList) => (
                          <SelectItem key={priceList.id} value={priceList.id ?? ''}>
                            {priceList.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                />
              </div>

              <div className="flex flex-col gap-2">
                <Label htmlFor="price-amount">Valor</Label>
                <Input id="price-amount" inputMode="decimal" {...register('amount', { required: true })} />
              </div>

              <label className="flex items-center gap-2 pb-2 text-sm">
                <input type="checkbox" className="border-input size-4 rounded" {...register('includes_vat')} />
                Inclui IVA
              </label>
            </div>

            {calculate.isSuccess && amount && calculate.data.amount === amount && (
              <p className="text-muted-foreground text-sm" data-testid="price-preview">
                {calculate.data.includes_vat ? 'Com IVA' : 'Sem IVA'}: {formatMoney(calculate.data.amount ?? '0')} ·{' '}
                {calculate.data.converted_includes_vat ? 'Com IVA' : 'Sem IVA'}:{' '}
                {formatMoney(calculate.data.converted_amount ?? '0')}
              </p>
            )}

            {setPrice.isError && (
              <p className="text-destructive text-sm" role="alert">
                {apiErrorMessage(setPrice.error, { 422: 'Valor inválido.', 403: 'Sem permissão para gerir produtos.' })}
              </p>
            )}

            <Button type="submit" disabled={setPrice.isPending || !priceListId} className="w-fit">
              {setPrice.isPending ? 'A guardar…' : 'Guardar preço'}
            </Button>
          </form>
        ) : (
          <p className="text-muted-foreground text-sm">Crie uma tabela de preços primeiro.</p>
        )}
      </CardContent>
    </Card>
  )
}
