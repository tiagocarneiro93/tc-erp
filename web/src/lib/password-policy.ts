// Mirrors api/src/Platform/Domain/PasswordPolicy.php -- docs/technical-scope.md §8.1.
// The Despacho itself sets no minimum length or complexity; this is the
// owner's own policy on top of it.

export const PASSWORD_MIN_LENGTH = 6

export const PASSWORD_POLICY_REGEX = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{6,}$/

export const PASSWORD_POLICY_ERROR_MESSAGE = 'A palavra-passe não cumpre os requisitos.'

export interface PasswordRule {
  label: string
  test: (password: string) => boolean
}

export const PASSWORD_RULES: PasswordRule[] = [
  { label: `Pelo menos ${PASSWORD_MIN_LENGTH} caracteres`, test: (p) => p.length >= PASSWORD_MIN_LENGTH },
  { label: 'Uma letra maiúscula', test: (p) => /[A-Z]/.test(p) },
  { label: 'Uma letra minúscula', test: (p) => /[a-z]/.test(p) },
  { label: 'Um número', test: (p) => /\d/.test(p) },
  { label: 'Um caráter especial', test: (p) => /[^A-Za-z\d]/.test(p) },
]
