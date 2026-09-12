/**
 * Nullable text fields (address, phone, …) round-trip through a plain HTML
 * input as an empty string; the API's DTOs want either the value or an
 * explicit `null` (never `""`) to clear a previously set field.
 */
export function blankToNull(value: string | undefined): string | null {
  return value && '' !== value ? value : null
}
