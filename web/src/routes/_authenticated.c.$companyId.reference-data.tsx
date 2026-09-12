import { createFileRoute } from '@tanstack/react-router'

import {
  useGetCountriesList,
  useGetDocumentTypesList,
  useGetExemptionReasonsList,
  useGetTaxRatesList,
  useGetUnitsList,
} from '@/api/generated'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDate, formatQuantity } from '@/lib/format/decimal'

export const Route = createFileRoute('/_authenticated/c/$companyId/reference-data')({
  component: ReferenceDataPage,
})

function ReferenceDataPage() {
  const countries = useGetCountriesList()
  const units = useGetUnitsList()
  const taxRates = useGetTaxRatesList()
  const exemptionReasons = useGetExemptionReasonsList()
  const documentTypes = useGetDocumentTypesList()

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-semibold">Dados de referência</h1>

      <Card>
        <CardHeader>
          <CardTitle>Taxas de IVA</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Região</TableHead>
                <TableHead>Código</TableHead>
                <TableHead>Percentagem</TableHead>
                <TableHead>Descrição</TableHead>
                <TableHead>Válida desde</TableHead>
                <TableHead>Válida até</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {taxRates.data?.items?.map((rate) => (
                <TableRow key={rate.id}>
                  <TableCell>{rate.region}</TableCell>
                  <TableCell>{rate.code}</TableCell>
                  <TableCell>{formatQuantity(rate.percentage ?? '0', 2)}%</TableCell>
                  <TableCell>{rate.description}</TableCell>
                  <TableCell>{rate.valid_from && formatDate(rate.valid_from)}</TableCell>
                  <TableCell>{rate.valid_to ? formatDate(rate.valid_to) : '—'}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Motivos de isenção</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Descrição</TableHead>
                <TableHead>Referência legal</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {exemptionReasons.data?.items?.map((reason) => (
                <TableRow key={reason.code}>
                  <TableCell>{reason.code}</TableCell>
                  <TableCell>{reason.description}</TableCell>
                  <TableCell>{reason.legal_reference}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Tipos de documento</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nome</TableHead>
                <TableHead>Secção SAF-T</TableHead>
                <TableHead>Assinado</TableHead>
                <TableHead>Efeito em stock</TableHead>
                <TableHead>Efeito contabilístico</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {documentTypes.data?.items?.map((type) => (
                <TableRow key={type.code}>
                  <TableCell>{type.code}</TableCell>
                  <TableCell>{type.name}</TableCell>
                  <TableCell>{type.saft_section}</TableCell>
                  <TableCell>{type.signed ? <Badge variant="outline">Sim</Badge> : 'Não'}</TableCell>
                  <TableCell>{type.stock_effect}</TableCell>
                  <TableCell>{type.account_effect}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Unidades</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nome</TableHead>
                <TableHead>Casas decimais</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {units.data?.items?.map((unit) => (
                <TableRow key={unit.code}>
                  <TableCell>{unit.code}</TableCell>
                  <TableCell>{unit.name}</TableCell>
                  <TableCell>{unit.decimals}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Países</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nome</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {countries.data?.items?.map((country) => (
                <TableRow key={country.code}>
                  <TableCell>{country.code}</TableCell>
                  <TableCell>{country.name}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
