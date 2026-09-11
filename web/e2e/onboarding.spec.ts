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
 * The task 0.13 acceptance flow: login, create a company, switch company.
 * Relies on `make seed` (task 0.12) having already run — there is no
 * self-registration endpoint, only invite and demo-seed accounts.
 */
test('login, create a company, then switch between companies', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill('owner@demo.tc-erp.test')
  await page.getByLabel('Palavra-passe').fill('demo-password')
  await page.getByRole('button', { name: 'Entrar' }).click()

  await expect(page).toHaveURL(/\/companies$/)
  await expect(page.getByText('Padaria Exemplo, Lda.')).toBeVisible()
  await expect(page.getByText('Exemplo Consultoria, Lda.')).toBeVisible()

  // Create a company. NIF must pass the real modulus-11 check digit.
  const nif = randomValidNif()
  await page.getByRole('button', { name: 'Nova empresa' }).click()
  await page.getByLabel('NIF').fill(nif)
  await page.getByLabel('Firma').fill('E2E Test Lda.')
  await page.getByRole('button', { name: 'Criar empresa' }).click()
  await expect(page.getByText('E2E Test Lda.')).toBeVisible()

  // Enter the first demo company.
  await page.getByText('Padaria Exemplo, Lda.').click()
  await expect(page).toHaveURL(/\/c\/[^/]+$/)
  await expect(page.getByRole('button', { name: 'Padaria Exemplo, Lda.' })).toBeVisible()

  // Switch to the second demo company via the company switcher.
  await page.getByRole('button', { name: 'Padaria Exemplo, Lda.' }).click()
  await page.getByRole('menuitem', { name: 'Exemplo Consultoria, Lda.' }).click()
  await expect(page.getByRole('button', { name: 'Exemplo Consultoria, Lda.' })).toBeVisible()
})
