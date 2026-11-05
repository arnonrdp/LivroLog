import { describe, expect, it } from 'vitest'
import { resolvePreferredRegion } from '../amazon'

describe('resolvePreferredRegion', () => {
  it('sends Brazilian books to amazon.com.br even when the viewer picked another store', () => {
    expect(resolvePreferredRegion('US', { language: 'pt-BR' })).toBe('BR')
    expect(resolvePreferredRegion('US', { isbn: '978-65-86070-62-0' })).toBe('BR')
    expect(resolvePreferredRegion('US', { isbn: '9788550708430' })).toBe('BR')
  })

  it('keeps the viewer store for other books', () => {
    expect(resolvePreferredRegion('US', { language: 'en', isbn: '9780307465351' })).toBe('US')
    expect(resolvePreferredRegion('DE', null)).toBe('DE')
  })
})
