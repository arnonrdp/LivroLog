import type { Book } from '@/models'

type Accessor = (b: Book) => string

const sortAccessors: Record<string, Accessor> = {
  authors: (b) => b.authors || '',
  addedIn: (b) => (b as any).addedIn || (b as any).added_at || (b as any).created_at || '',
  added_at: (b) => (b as any).addedIn || (b as any).added_at || (b as any).created_at || '',
  created_at: (b) => (b as any).addedIn || (b as any).added_at || (b as any).created_at || '',
  readIn: (b) => (b as any).readIn || (b as any).read_at || (b as any).pivot?.read_at || '',
  read_at: (b) => (b as any).readIn || (b as any).read_at || (b as any).pivot?.read_at || '',
  title: (b) => b.title || ''
}

export function sortBooks(books: Book[], sortKey: string | number, ascDesc: string): Book[] {
  const getValue = sortAccessors[sortKey as string] ?? sortAccessors.title
  return books.slice().sort((a, b) => {
    const aVal = getValue(a)
    const bVal = getValue(b)
    if (aVal === bVal) return 0
    return aVal > bVal === (ascDesc === 'asc') ? 1 : -1
  })
}
