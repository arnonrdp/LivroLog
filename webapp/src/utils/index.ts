import type { Book } from '@/models'

export function sortBooks(books: Book[], sortKey: string | number, ascDesc: string): Book[] {
  return books.slice().sort((a: any, b: any) => {
    let aValue = ''
    let bValue = ''
    switch (sortKey) {
      case 'authors':
        aValue = a.authors || ''
        bValue = b.authors || ''
        break
      case 'addedIn':
      case 'added_at':
      case 'created_at':
        aValue = a.addedIn || a.added_at || a.created_at || ''
        bValue = b.addedIn || b.added_at || b.created_at || ''
        break
      case 'readIn':
      case 'read_at':
        aValue = a.readIn || a.read_at || (a.pivot && a.pivot.read_at) || ''
        bValue = b.readIn || b.read_at || (b.pivot && b.pivot.read_at) || ''
        break
      case 'title':
      default:
        aValue = a.title || ''
        bValue = b.title || ''
    }
    if (aValue > bValue) return ascDesc === 'asc' ? 1 : -1
    if (aValue < bValue) return ascDesc === 'asc' ? -1 : 1
    return 0
  })
}
