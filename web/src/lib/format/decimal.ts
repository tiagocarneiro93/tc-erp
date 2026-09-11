/**
 * pt-PT formatting for values the API sends as decimal strings (CLAUDE.md:
 * "Never use float for money, quantities, prices, rates or percentages ...
 * strings in JSON and TypeScript"). These never convert to a JS `number` —
 * a double can't represent every decimal exactly, and formatting is exactly
 * the kind of place a silent precision loss would go unnoticed.
 *
 * Assumes the input is already at its canonical precision, as every such
 * value from this API is (fixed by `PriceCalculator`/Doctrine's DECIMAL
 * columns) — this only pads a short fraction with zeros or truncates a
 * longer one; it does not round.
 */

interface DecimalParts {
  negative: boolean
  integer: string
  fraction: string
}

function isZero(integer: string, fraction: string): boolean {
  return /^0*$/.test(integer) && /^0*$/.test(fraction)
}

function parseDecimalString(value: string, decimals: number): DecimalParts {
  const trimmed = value.trim()
  const negative = trimmed.startsWith('-')
  const unsigned = negative || trimmed.startsWith('+') ? trimmed.slice(1) : trimmed
  const [integerPart, fractionPart] = unsigned.split('.')
  const integer = (integerPart ?? '0').replace(/^0+(?=\d)/, '') || '0'
  const fraction = (fractionPart ?? '').slice(0, decimals).padEnd(decimals, '0')

  return { negative: negative && !isZero(integer, fraction), integer, fraction }
}

function groupThousands(digits: string): string {
  return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
}

/**
 * @param value a decimal string, e.g. "1234.5"
 * @param currency the symbol to append; defaults to the euro sign
 */
export function formatMoney(value: string, currency = '€'): string {
  const { negative, integer, fraction } = parseDecimalString(value, 2)

  return `${negative ? '-' : ''}${groupThousands(integer)},${fraction} ${currency}`
}

/**
 * @param value a decimal string, e.g. "12.5"
 * @param decimals how many decimal places to show (technical-scope.md §6: quantities are NUMERIC(19,6), but most units display far fewer)
 */
export function formatQuantity(value: string, decimals = 3): string {
  const { negative, integer, fraction } = parseDecimalString(value, decimals)
  const sign = negative ? '-' : ''

  return decimals > 0 ? `${sign}${groupThousands(integer)},${fraction}` : `${sign}${groupThousands(integer)}`
}

/**
 * @param value an ISO 8601 date or date-time string
 */
export function formatDate(value: string): string {
  return new Intl.DateTimeFormat('pt-PT', { dateStyle: 'medium' }).format(new Date(value))
}
