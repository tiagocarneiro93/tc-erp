import { expect, test } from '@playwright/test'

function randomValidNif(): string {
  const prefix = String(10_000_000 + Math.floor(Math.random() * 89_999_999))
  const sum = prefix.split('').reduce((total, digit, index) => total + Number(digit) * (9 - index), 0)
  const remainder = sum % 11
  const checkDigit = remainder < 2 ? 0 : 11 - remainder

  return `${prefix}${checkDigit}`
}

/**
 * docs/plans/phase-2.md task 2.11's own accept criterion, verbatim:
 * "create a draft, see live totals update as lines change, issue it, see
 * it appear read-only in the document list, issue a credit note against
 * it, create a partial-conversion working document and convert it, issue
 * a receipt allocated across two invoices." A fresh company (same
 * isolation reasoning as `master-data.spec.ts`) keeps this independent of
 * whatever earlier runs left in the seeded demo companies. The one
 * product used throughout is exempt (ISE/M99) so `unit_price` equals
 * `gross_total` line for line — this test exercises the fiscal
 * *workflow*, not VAT arithmetic, which `PriceCalculator`'s own test
 * vectors (task 2.1) already cover exhaustively.
 */
test('draft → issue → credit note → partial conversion → receipt across two invoices', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill('owner@demo.tc-erp.test')
  await page.getByLabel('Palavra-passe').fill('demo-password')
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page).toHaveURL(/\/companies$/)

  const nif = randomValidNif()
  const companyName = `E2E Fiscal Docs ${Date.now()}`
  await page.getByRole('button', { name: 'Nova empresa' }).click()
  await page.getByLabel('NIF').fill(nif)
  await page.getByLabel('Firma').fill(companyName)
  await page.getByRole('button', { name: 'Criar empresa' }).click()
  await expect(page.getByText(companyName)).toBeVisible()

  await page.reload()
  await expect(page.getByText(companyName)).toBeVisible()
  await page.getByText(companyName).click()
  await expect(page).toHaveURL(/\/c\/[^/]+$/)

  // A product to put on every line below — exempt, so 1 unit at price X
  // always means a gross_total of exactly X.
  await page.getByRole('link', { name: 'Produtos' }).click()
  await page.getByRole('button', { name: 'Novo produto' }).click()
  await page.getByLabel('Código', { exact: true }).fill('E2E1')
  await page.getByLabel('Descrição').fill('Produto E2E')
  await page.getByLabel('Unidade').click()
  await page.getByRole('option', { name: 'UN' }).click()
  await page.getByLabel('Taxa de IVA').click()
  await page.getByRole('option', { name: 'Isento' }).click()
  await page.getByLabel(/Motivo de isenção/).click()
  await page.getByRole('option', { name: /^M99/ }).click()
  await page.getByRole('button', { name: 'Criar produto' }).click()
  await expect(page.getByText('Produto E2E')).toBeVisible()

  // Series: FT, NC, NE and RG, each activated with a dev-made-up
  // validation code (the real AT webservice call is Phase 3).
  const createAndActivateSeries = async (documentType: string, code: string) => {
    await page.getByRole('link', { name: 'Séries' }).click()
    await page.getByRole('button', { name: 'Nova série' }).click()
    const createDialog = page.getByRole('dialog')
    await createDialog.getByLabel('Tipo de documento').click()
    await page.getByRole('option', { name: new RegExp(`^${documentType} —`) }).click()
    await createDialog.getByLabel('Código').fill(code)
    await createDialog.getByRole('button', { name: 'Criar série' }).click()
    await expect(page.getByRole('cell', { name: code })).toBeVisible()

    await page.getByRole('row').filter({ hasText: code }).getByRole('button', { name: 'Ativar' }).click()
    const activateDialog = page.getByRole('dialog')
    await activateDialog.getByLabel('Código de validação (AT)').fill('E2E-VALIDATION')
    await activateDialog.getByRole('button', { name: 'Ativar', exact: true }).click()
    await expect(page.getByRole('row').filter({ hasText: code }).getByText('Ativa')).toBeVisible()
  }

  await createAndActivateSeries('FT', '2026FT')
  await createAndActivateSeries('NC', '2026NC')
  await createAndActivateSeries('NE', '2026NE')
  await createAndActivateSeries('RG', '2026RG')

  const issueInvoice = async (quantity: string, unitPrice: string): Promise<string> => {
    await page.getByRole('link', { name: 'Documentos' }).click()
    await page.getByRole('button', { name: 'Novo documento' }).click()
    await page.getByRole('combobox', { name: 'Tipo de documento' }).click()
    await page.getByRole('option', { name: /^FT —/ }).click()
    await page.getByRole('button', { name: 'Criar rascunho' }).click()
    await expect(page).toHaveURL(/\/documents\/drafts\/[^/]+$/)

    await page.getByLabel('Série').click()
    await page.getByRole('option', { name: '2026FT' }).click()

    await page.getByRole('button', { name: 'Adicionar linha' }).click()
    await page.getByLabel('Produto').click()
    await page.getByRole('option', { name: 'E2E1 — Produto E2E' }).click()
    await page.getByLabel('Quantidade').fill(quantity)
    await page.getByLabel('Preço unitário').fill(unitPrice)

    // Live totals: wait for the debounced /calculate call, then confirm a
    // quantity change actually moves the total (never computed here —
    // technical-scope.md §7.9, CLAUDE.md "no fiscal logic in the browser").
    await expect(page.getByTestId('draft-total')).toBeVisible({ timeout: 10_000 })
    const firstTotal = await page.getByTestId('draft-total').innerText()
    await page.getByLabel('Quantidade').fill(String(2 * Number(quantity)))
    await expect(page.getByTestId('draft-total')).not.toHaveText(firstTotal, { timeout: 10_000 })
    await page.getByLabel('Quantidade').fill(quantity)
    await expect(page.getByTestId('draft-total')).toHaveText(firstTotal, { timeout: 10_000 })

    await page.getByRole('button', { name: 'Emitir documento' }).click()
    await expect(page).toHaveURL(/\/documents\/(?!drafts)[^/]+$/, { timeout: 10_000 })
    const documentNo = await page.getByRole('heading', { level: 1 }).innerText()

    // Read-only in the document list.
    await page.getByRole('link', { name: 'Documentos' }).click()
    await expect(page.getByRole('link', { name: documentNo })).toBeVisible()

    return documentNo
  }

  const invoice1 = await issueInvoice('1', '100.00')
  const invoice2 = await issueInvoice('1', '50.00')

  // Credit note against the first invoice.
  await page.getByRole('link', { name: invoice1 }).click()
  await page.getByRole('button', { name: 'Nota de crédito' }).click()
  await expect(page).toHaveURL(/\/documents\/drafts\/[^/]+$/)
  await page.getByLabel('Série').click()
  await page.getByRole('option', { name: '2026NC' }).click()
  await expect(page.getByTestId('draft-total')).toBeVisible({ timeout: 10_000 })
  await page.getByRole('button', { name: 'Emitir documento' }).click()
  await expect(page).toHaveURL(/\/documents\/(?!drafts)[^/]+$/, { timeout: 10_000 })

  // A working document (NE), partially converted into a new FT.
  await page.getByRole('link', { name: 'Documentos' }).click()
  await page.getByRole('button', { name: 'Novo documento' }).click()
  await page.getByRole('combobox', { name: 'Tipo de documento' }).click()
  await page.getByRole('option', { name: /^NE —/ }).click()
  await page.getByRole('button', { name: 'Criar rascunho' }).click()
  await expect(page).toHaveURL(/\/documents\/drafts\/[^/]+$/)
  await expect(page.getByText('Este documento não serve de fatura')).toBeVisible()
  await page.getByLabel('Série').click()
  await page.getByRole('option', { name: '2026NE' }).click()
  await page.getByRole('button', { name: 'Adicionar linha' }).click()
  await page.getByLabel('Produto').click()
  await page.getByRole('option', { name: 'E2E1 — Produto E2E' }).click()
  await page.getByLabel('Quantidade').fill('10')
  await page.getByLabel('Preço unitário').fill('5.00')
  await expect(page.getByTestId('draft-total')).toBeVisible({ timeout: 10_000 })
  await page.getByRole('button', { name: 'Emitir documento' }).click()
  await expect(page).toHaveURL(/\/documents\/(?!drafts)[^/]+$/, { timeout: 10_000 })

  await page.getByRole('button', { name: 'Converter' }).click()
  await page.getByRole('combobox', { name: 'Tipo de destino' }).click()
  await page.getByRole('option', { name: 'FT' }).click()
  await page.getByRole('button', { name: 'Converter', exact: true }).click()
  await expect(page).toHaveURL(/\/documents\/drafts\/[^/]+$/, { timeout: 10_000 })
  // Partial conversion: reduce the prefilled (fully pending) quantity.
  await expect(page.getByLabel('Quantidade')).toHaveValue('10.000000')
  await page.getByLabel('Quantidade').fill('4')
  await page.getByLabel('Série').click()
  await page.getByRole('option', { name: '2026FT' }).click()
  await expect(page.getByTestId('draft-total')).toBeVisible({ timeout: 10_000 })
  await page.getByRole('button', { name: 'Emitir documento' }).click()
  await expect(page).toHaveURL(/\/documents\/(?!drafts)[^/]+$/, { timeout: 10_000 })

  // A receipt allocated across the two original invoices.
  await page.getByRole('link', { name: 'Recibos' }).click()
  await page.getByRole('button', { name: 'Novo recibo' }).click()
  const receiptDialog = page.getByRole('dialog')
  await receiptDialog.getByLabel('Série').click()
  await page.getByRole('option', { name: '2026RG' }).click()
  await receiptDialog.getByLabel('Forma de pagamento').fill('transferência')

  const allocateFullAmount = async (documentNo: string) => {
    await receiptDialog.getByRole('row').filter({ hasText: documentNo }).getByRole('checkbox').check()
  }

  await allocateFullAmount(invoice1)
  await allocateFullAmount(invoice2)
  await receiptDialog.getByRole('button', { name: 'Emitir recibo' }).click()
  await expect(page.getByRole('dialog')).not.toBeVisible({ timeout: 10_000 })
  await expect(page.getByRole('cell', { name: '150,00 €' })).toBeVisible()
})
