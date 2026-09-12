import type { ApiError } from '@/api/http-client'

/**
 * Every master-data form branches its error banner on the response's HTTP
 * status rather than the RFC 9457 `type` field (technical-scope.md §9:
 * stable `type` codes exist, but the status code alone is precise enough
 * for "this NIF is taken" vs. "fix the form" vs. "try again" at this UI
 * granularity — see `CreateCompanyForm`/`InviteMemberForm` for the
 * pre-existing per-form version of this same switch).
 */
export function apiErrorMessage(error: ApiError, messagesByStatus: Partial<Record<number, string>>): string {
  return messagesByStatus[error.status] ?? 'Ocorreu um erro. Tente novamente.'
}
