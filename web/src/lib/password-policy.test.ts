import { describe, expect, it } from 'vitest'

import { PASSWORD_POLICY_REGEX, PASSWORD_RULES } from './password-policy'

describe('PASSWORD_POLICY_REGEX', () => {
  it.each(['Abcde1!', 'Correct-Horse-Battery-1!', 'Passw0rd$'])('accepts %s', (password) => {
    expect(PASSWORD_POLICY_REGEX.test(password)).toBe(true)
  })

  it.each(['Ab1!', 'abcde1!', 'ABCDE1!', 'Abcdef!', 'Abcdef1', ''])('rejects %s', (password) => {
    expect(PASSWORD_POLICY_REGEX.test(password)).toBe(false)
  })
})

describe('PASSWORD_RULES', () => {
  it('flags every rule as unmet for an empty password', () => {
    expect(PASSWORD_RULES.every((rule) => !rule.test(''))).toBe(true)
  })

  it('flags every rule as met for a password satisfying the whole policy', () => {
    expect(PASSWORD_RULES.every((rule) => rule.test('Abcde1!'))).toBe(true)
  })
})
