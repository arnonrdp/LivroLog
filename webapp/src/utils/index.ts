import type { Book, Tag } from '@/models'

type Accessor = (b: Book) => string

const sortAccessors: Record<string, Accessor> = {
  authors: (b) => b.authors || '',
  addedIn: (b) => String(b.pivot?.added_at || b.created_at || ''),
  added_at: (b) => String(b.pivot?.added_at || b.created_at || ''),
  created_at: (b) => String(b.created_at || ''),
  readIn: (b) => String(b.pivot?.read_at || ''),
  read_at: (b) => String(b.pivot?.read_at || ''),
  tags: (b) => {
    // Sort by first tag name alphabetically, books without tags go last
    const tags = (b as BookWithTags).tags || []
    if (tags.length === 0) return '\uffff' // Unicode max to sort last
    const sortedTags = [...tags].sort((a, b) => a.name.localeCompare(b.name))
    return sortedTags[0]?.name || '\uffff'
  },
  title: (b) => b.title || ''
}

// Extended Book type with tags
interface BookWithTags extends Book {
  tags?: Tag[]
}

export function sortBooks(books: Book[], sortKey: string | number, ascDesc: string): Book[] {
  const getValue = sortAccessors[sortKey as string] ?? sortAccessors.title
  return books.slice().sort((a, b) => {
    const aVal = getValue!(a)
    const bVal = getValue!(b)
    if (aVal === bVal) return 0
    return aVal > bVal === (ascDesc === 'asc') ? 1 : -1
  })
}
