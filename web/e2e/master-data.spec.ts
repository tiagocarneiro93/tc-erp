import { expect, test } from '@playwright/test'

function randomValidNif(): string {
  const prefix = String(10_000_000 + Math.floor(Math.random() * 89_999_999))
  const sum = prefix
    .split('')
    .reduce((total, digit, index) => total + Number(digit) * (9 - index), 0)
  const remainder = sum % 11
  const checkDigit = remainder < 2 ? 0 : 11 - remainder

  return `${prefix}${checkDigit}`
}

/**
 * The task 1.10 acceptance flow (docs/plans/phase-1.md): create a customer,
 * create a product with a price (verify the live net/gross preview), build
 * a kit from two existing products, create a warehouse, switch company and
 * confirm the lists change. Relies on `make seed` (task 0.12) for the demo
 * owner login, same as `onboarding.spec.ts`.
 */
test('customers, products with prices, kits, warehouses, and company isolation', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill('owner@demo.tc-erp.test')
  await page.getByLabel('Palavra-passe').fill('demo-password')
  await page.getByRole('button', { name: 'Entrar' }).click()
  await expect(page).toHaveURL(/\/companies$/)

  // A fresh company keeps this test independent of whatever earlier runs
  // left in the two seeded demo companies.
  const nif = randomValidNif()
  const companyName = `E2E Master Data ${Date.now()}`
  await page.getByRole('button', { name: 'Nova empresa' }).click()
  await page.getByLabel('NIF').fill(nif)
  await page.getByLabel('Firma').fill(companyName)
  await page.getByRole('button', { name: 'Criar empresa' }).click()
  await expect(page.getByText(companyName)).toBeVisible()

  // A full reload guarantees the `/me` companies list (which
  // `/c/$companyId`'s membership guard reads) is refetched together with
  // the page itself — clicking straight in right after creation can race
  // ahead of that cache's own invalidation and bounce back here.
  await page.reload()
  await expect(page.getByText(companyName)).toBeVisible()
  await page.getByText(companyName).click()
  await expect(page).toHaveURL(/\/c\/[^/]+$/)

  // 1. Create a customer.
  await page.getByRole('link', { name: 'Clientes' }).click()
  await page.getByRole('button', { name: 'Novo cliente' }).click()
  await page.getByLabel('Código', { exact: true }).fill('CLI1')
  await page.getByLabel('NIF').fill(randomValidNif())
  await page.getByLabel('Nome').fill('Cliente E2E')
  await page.getByRole('button', { name: 'Criar cliente' }).click()
  await expect(page.getByText('Cliente E2E')).toBeVisible()

  // 2. Create a price list (needed before a product can carry a price).
  await page.getByRole('link', { name: 'Tabelas de preços' }).click()
  await page.getByRole('button', { name: 'Nova tabela de preços' }).click()
  await page.getByLabel('Nome').fill('Tabela Base')
  await page.getByRole('button', { name: 'Criar tabela de preços' }).click()
  await expect(page.getByText('Tabela Base')).toBeVisible()

  // 3. Create two simple products (future kit components) and a kit.
  await page.getByRole('link', { name: 'Produtos' }).click()

  const createProduct = async (code: string, description: string, kind: 'Simples' | 'Kit') => {
    await page.getByRole('button', { name: 'Novo produto' }).click()
    await page.getByLabel('Código', { exact: true }).fill(code)
    await page.getByLabel('Descrição').fill(description)
    await page.getByLabel('Género').click()
    await page.getByRole('option', { name: kind }).click()
    await page.getByLabel('Unidade').click()
    await page.getByRole('option', { name: 'UN' }).click()
    await page.getByLabel('Taxa de IVA').click()
    await page.getByRole('option').first().click()
    await page.getByRole('button', { name: 'Criar produto' }).click()
    await expect(page.getByText(description)).toBeVisible()
  }

  await createProduct('COMP1', 'Componente Um', 'Simples')
  await createProduct('COMP2', 'Componente Dois', 'Simples')
  await createProduct('KIT1', 'Kit E2E', 'Kit')

  // 4. Open "Componente Um" (code COMP1) and set a price, checking the live preview.
  await page.getByRole('link', { name: 'COMP1' }).click()
  await expect(page).toHaveURL(/\/products\/[^/]+$/)
  await page.getByLabel('Tabela de preços').click()
  await page.getByRole('option', { name: 'Tabela Base' }).click()
  await page.getByLabel('Valor').fill('9.99')
  await expect(page.getByTestId('price-preview')).toBeVisible()
  await expect(page.getByTestId('price-preview')).toContainText('9,99')
  await page.getByRole('button', { name: 'Guardar preço' }).click()
  await expect(page.getByText('9,99 €')).toBeVisible()

  // 5. Build the kit from the two existing products.
  await page.getByRole('link', { name: '← Produtos' }).click()
  await page.getByRole('link', { name: 'KIT1' }).click()
  await expect(page.getByText('Composição do kit')).toBeVisible()

  const addComponent = async (productLabel: string, quantity: string) => {
    await page.getByLabel('Produto').click()
    await page.getByRole('option', { name: productLabel }).click()
    await page.getByLabel('Quantidade').fill(quantity)
    await page.getByRole('button', { name: 'Adicionar componente' }).click()
  }

  await addComponent('COMP1 — Componente Um', '2')
  await expect(page.getByRole('cell', { name: 'Componente Um' })).toBeVisible()
  await addComponent('COMP2 — Componente Dois', '1')
  await expect(page.getByRole('cell', { name: 'Componente Dois' })).toBeVisible()

  // 6. Create a warehouse.
  await page.getByRole('link', { name: 'Armazéns' }).click()
  await page.getByRole('button', { name: 'Novo armazém' }).click()
  await page.getByLabel('Código', { exact: true }).fill('ARM1')
  await page.getByLabel('Nome').fill('Armazém E2E')
  await page.getByRole('button', { name: 'Criar armazém' }).click()
  await expect(page.getByText('Armazém E2E')).toBeVisible()

  // 7. Switch company and confirm the lists change: none of this
  // company's master data leaks into a different one.
  await page.getByRole('button', { name: companyName }).click()
  await page.getByRole('menuitem', { name: 'Padaria Exemplo, Lda.' }).click()
  await expect(page.getByRole('button', { name: 'Padaria Exemplo, Lda.' })).toBeVisible()

  await page.getByRole('link', { name: 'Clientes' }).click()
  await expect(page.getByText('Cliente E2E')).not.toBeVisible()

  await page.getByRole('link', { name: 'Armazéns' }).click()
  await expect(page.getByText('Armazém E2E')).not.toBeVisible()
})
