/**
 * Matches docs/decisions/0002-roles-and-permissions.md — there is no
 * "list roles" endpoint, and this fixed reference list changes about as
 * often as the API contract itself does.
 */
export const ROLES = [
  { code: 'owner', label: 'Proprietário' },
  { code: 'admin', label: 'Administrador' },
  { code: 'billing', label: 'Faturação' },
  { code: 'stock', label: 'Stock' },
  { code: 'accountant', label: 'Contabilista' },
  { code: 'read_only', label: 'Apenas leitura' },
] as const
