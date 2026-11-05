import type { Book } from '@/models'
import { describe, expect, it } from 'vitest'
import { sortBooks } from '..'

const book = (id: string, pivot: Partial<NonNullable<Book['pivot']>>) => ({ id, title: id, pivot: { book_id: id, ...pivot } }) as Book
const ids = (books: Book[]) => books.map((b) => b.id)

describe('sortBooks', () => {
  const books = [
    book('unread', {}),
    book('2024', { read_at: '2024-05-01T00:00:00.000000Z' }),
    book('2026', { read_at: '2026-01-10' }),
    book('2025', { read_at: '2025-12-31T00:00:00.000000Z' })
  ]

  it('orders by read date and keeps books without a date last in both directions', () => {
    expect(ids(sortBooks(books, 'readIn', 'desc'))).toEqual(['2026', '2025', '2024', 'unread'])
    expect(ids(sortBooks(books, 'readIn', 'asc'))).toEqual(['2024', '2025', '2026', 'unread'])
  })

  it('orders custom positions numerically, unpositioned books last', () => {
    const custom = [book('a', { position: 10 }), book('b', { position: null }), book('c', { position: 2 }), book('d', { position: 0 })]
    expect(ids(sortBooks(custom, 'custom', 'asc'))).toEqual(['d', 'c', 'a', 'b'])
  })
})
