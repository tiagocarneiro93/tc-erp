import { describe, expect, it } from 'vitest'

import { formatDate, formatMoney, formatQuantity } from './decimal'

describe('formatMoney', () => {
  it('formats a whole number with two decimal places', () => {
    expect(formatMoney('1234')).toBe('1.234,00 €')
  })

  it('pads a short fraction', () => {
    expect(formatMoney('1234.5')).toBe('1.234,50 €')
  })

  it('truncates a longer fraction without rounding', () => {
    expect(formatMoney('1234.567')).toBe('1.234,56 €')
  })

  it('groups thousands with a dot, pt-PT style', () => {
    expect(formatMoney('1000000')).toBe('1.000.000,00 €')
  })

  it('formats a negative amount', () => {
    expect(formatMoney('-42.5')).toBe('-42,50 €')
  })

  it('never shows a negative sign for a value that rounds to zero', () => {
    expect(formatMoney('-0')).toBe('0,00 €')
  })

  it('accepts a different currency symbol', () => {
    expect(formatMoney('10', '$')).toBe('10,00 $')
  })
})

describe('formatQuantity', () => {
  it('defaults to three decimal places', () => {
    expect(formatQuantity('12.5')).toBe('12,500')
  })

  it('supports a custom precision', () => {
    expect(formatQuantity('12.5', 2)).toBe('12,50')
  })

  it('supports zero decimal places', () => {
    expect(formatQuantity('12.9', 0)).toBe('12')
  })
})

describe('formatDate', () => {
  it('formats an ISO date string using pt-PT', () => {
    expect(formatDate('2026-09-11')).toContain('2026')
  })
})
