import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Book } from '@/models'

// Hoist mock before imports
const mockAxios = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  delete: vi.fn()
}))

vi.mock('@/utils/axios', () => ({
  default: mockAxios
}))

import { useBookStore } from '../book'

// Mock i18n
vi.mock('@/locales', () => ({
  i18n: {
    global: {
      t: (key: string) => key
    }
  }
}))

describe('Book Store', () => {
  const mockBook: Book = {
    id: 'B-TEST-1234',
    title: 'Test Book',
    authors: 'Test Author',
    isbn: '9781234567890',
    description: 'A test book description',
    thumbnail: 'https://example.com/cover.jpg',
    page_count: 300,
    language: 'pt'
  }

  const mockBooks: Book[] = [
    mockBook,
    {
      id: 'B-TEST-5678',
      title: 'Another Book',
      authors: 'Another Author',
      isbn: '9780987654321'
    }
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useBookStore()

      expect(store._book).toBeNull()
      expect(store._books).toEqual([])
      expect(store._isLoading).toBe(false)
    })
  })

  describe('Getters', () => {
    it('book getter should return state value', () => {
      const store = useBookStore()
      expect(store.book).toBeNull()

      store._book = mockBook
      expect(store.book).toEqual(mockBook)
    })

    it('books getter should return state value', () => {
      const store = useBookStore()
      expect(store.books).toEqual([])

      store._books = mockBooks
      expect(store.books).toEqual(mockBooks)
    })

    it('isLoading getter should return state value', () => {
      const store = useBookStore()
      expect(store.isLoading).toBe(false)

      store._isLoading = true
      expect(store.isLoading).toBe(true)
    })
  })

  describe('getBooks', () => {
    it('should fetch books list', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: mockBooks } })

      const store = useBookStore()
      await store.getBooks()

      expect(mockAxios.get).toHaveBeenCalledWith('/books', { params: {} })
      expect(store._books).toEqual(mockBooks)
      expect(store._isLoading).toBe(false)
    })

    it('should fetch books with search param', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: [mockBook] } })

      const store = useBookStore()
      const result = await store.getBooks({ search: 'test' })

      expect(mockAxios.get).toHaveBeenCalledWith('/books', { params: { search: 'test' } })
      expect(result).toEqual([mockBook])
      expect(store._isLoading).toBe(false)
    })

    it('should handle books response in different formats', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { books: mockBooks } })

      const store = useBookStore()
      await store.getBooks()

      expect(store._books).toEqual(mockBooks)
    })

    it('should handle error when fetching books fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Server error' } }
      })

      const store = useBookStore()
      await store.getBooks()

      expect(store._isLoading).toBe(false)
    })
  })

  describe('getBook', () => {
    it('should fetch single book by id', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockBook })

      const store = useBookStore()
      const result = await store.getBook('B-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/books/B-TEST-1234', { params: {} })
      expect(result).toEqual(mockBook)
      expect(store._book).toEqual(mockBook)
      expect(store._isLoading).toBe(false)
    })

    it('should fetch book with related data', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockBook })

      const store = useBookStore()
      await store.getBook('B-TEST-1234', { with: ['reviews', 'author'] })

      expect(mockAxios.get).toHaveBeenCalledWith('/books/B-TEST-1234', {
        params: { 'with[]': ['reviews', 'author'] }
      })
    })

    it('should fetch book for specific user', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockBook })

      const store = useBookStore()
      await store.getBook('B-TEST-1234', { user_id: 'U-TEST-1234' })

      expect(mockAxios.get).toHaveBeenCalledWith('/books/B-TEST-1234', {
        params: { user_id: 'U-TEST-1234' }
      })
    })

    it('should handle error when fetching book fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Book not found' } }
      })

      const store = useBookStore()

      await expect(store.getBook('B-INVALID')).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('postBook', () => {
    it('should create a new book', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: mockBook })

      const store = useBookStore()
      const payload = {
        title: 'Test Book',
        authors: 'Test Author',
        isbn: '9781234567890'
      }
      const result = await store.postBook(payload)

      expect(mockAxios.post).toHaveBeenCalledWith('/books', payload)
      expect(result).toEqual(mockBook)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when creating book fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Validation error' } }
      })

      const store = useBookStore()

      await expect(store.postBook({ title: '' })).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('putBook', () => {
    it('should update an existing book', async () => {
      const updatedBook = { ...mockBook, title: 'Updated Title' }
      mockAxios.put.mockResolvedValueOnce({ data: updatedBook })

      const store = useBookStore()
      store._books = [mockBook]

      await store.putBook('B-TEST-1234', { title: 'Updated Title' })

      expect(mockAxios.put).toHaveBeenCalledWith('/books/B-TEST-1234', { title: 'Updated Title' })
      expect(store._books[0].title).toBe('Updated Title')
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when updating book fails', async () => {
      mockAxios.put.mockRejectedValueOnce({
        response: { data: { message: 'Update failed' } }
      })

      const store = useBookStore()

      await store.putBook('B-TEST-1234', { title: 'New Title' })

      expect(store._isLoading).toBe(false)
    })
  })

  describe('deleteBook', () => {
    it('should delete a book', async () => {
      mockAxios.delete.mockResolvedValueOnce({ data: {} })

      const store = useBookStore()
      store._books = [mockBook, mockBooks[1]]

      await store.deleteBook('B-TEST-1234')

      expect(mockAxios.delete).toHaveBeenCalledWith('/books/B-TEST-1234')
      expect(store._books).toHaveLength(1)
      expect(store._books.find((b) => b.id === 'B-TEST-1234')).toBeUndefined()
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when deleting book fails', async () => {
      mockAxios.delete.mockRejectedValueOnce({
        response: { data: { message: 'Delete failed' } }
      })

      const store = useBookStore()
      store._books = [mockBook]

      await store.deleteBook('B-TEST-1234')

      expect(store._books).toHaveLength(1) // Book should still be in list
      expect(store._isLoading).toBe(false)
    })
  })

  describe('getBookEditions', () => {
    it('should fetch book editions', async () => {
      const editions = [mockBook, { ...mockBook, id: 'B-TEST-EDITION' }]
      mockAxios.get.mockResolvedValueOnce({ data: editions })

      const store = useBookStore()
      const result = await store.getBookEditions('B-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/books/B-TEST-1234/editions')
      expect(result).toEqual(editions)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when fetching editions fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Error fetching editions' } }
      })

      const store = useBookStore()

      await expect(store.getBookEditions('B-TEST-1234')).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })
})
