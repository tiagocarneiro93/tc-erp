import { useGetCountriesList } from '@/api/generated'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface CountrySelectProps {
  id?: string
  value: string
  onValueChange: (value: string) => void
}

export function CountrySelect({ id, value, onValueChange }: CountrySelectProps) {
  const { data } = useGetCountriesList()
  const countries = data?.items ?? []

  return (
    <Select value={value} onValueChange={onValueChange}>
      <SelectTrigger id={id}>
        <SelectValue placeholder="Escolha o país" />
      </SelectTrigger>
      <SelectContent>
        {countries.map((country) => (
          <SelectItem key={country.code} value={country.code ?? ''}>
            {country.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
