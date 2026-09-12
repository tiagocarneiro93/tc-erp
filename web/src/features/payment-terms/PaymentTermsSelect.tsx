import { useGetPaymentTermsList } from '@/api/generated'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const NONE = '__none__'

interface PaymentTermsSelectProps {
  id?: string
  companyId: string
  value: string
  onValueChange: (value: string) => void
}

export function PaymentTermsSelect({ id, companyId, value, onValueChange }: PaymentTermsSelectProps) {
  const { data } = useGetPaymentTermsList(companyId)
  const paymentTerms = (data?.items ?? []).filter((item) => item.active)

  return (
    <Select value={value === '' ? NONE : value} onValueChange={(next) => onValueChange(next === NONE ? '' : next)}>
      <SelectTrigger id={id}>
        <SelectValue placeholder="Escolha o prazo de pagamento" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={NONE}>Sem prazo definido</SelectItem>
        {paymentTerms.map((item) => (
          <SelectItem key={item.id} value={item.id ?? ''}>
            {item.name} ({item.days} dias)
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
