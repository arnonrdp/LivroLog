import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Book } from '@/models'

// Hoist mock before imports
const mockAxios = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn()
}))

vi.mock('@/utils/axios', () => ({
  default: mockAxios
}))

import { useUserBookStore } from '../userbook'
import { useUserStore } from '../user'

// Mock i18n
vi.mock('@/locales', () => ({
  i18n: {
    global: {
      t: (key: string) => key
    }
  }
}))

describe('UserBook Store', () => {
  const mockBook: Book = {
    id: 'B-TEST-1234',
    title: 'Test Book',
    authors: 'Test Author',
    isbn: '9781234567890',
    google_id: 'google-123',
    pivot: {
      reading_status: 'read',
      read_at: '2024-01-01',
      is_private: false
    }
  }

  const mockBooks: Book[] = [
    mockBook,
    {
      id: 'B-TEST-5678',
      title: 'Another Book',
      authors: 'Another Author',
      pivot: {
        reading_status: 'reading',
        is_private: false
      }
    }
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useUserBookStore()

      expect(store._book).toEqual({})
      expect(store._isLoading).toBe(false)
    })
  })

  describe('Getters', () => {
    it('book getter should return state value', () => {
      const store = useUserBookStore()

      store._book = mockBook
      expect(store.book).toEqual(mockBook)
    })

    it('isLoading getter should return state value', () => {
      const store = useUserBookStore()
      expect(store.isLoading).toBe(false)

      store._isLoading = true
      expect(store.isLoading).toBe(true)
    })
  })

  describe('getUserBooks', () => {
    it('should fetch user library', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockBooks })

      const store = useUserBookStore()
      const userStore = useUserStore()

      const result = await store.getUserBooks()

      expect(mockAxios.get).toHaveBeenCalledWith('/user/books')
      expect(result).toEqual(mockBooks)
      expect(userStore.me.books).toEqual(mockBooks)
      expect(store._isLoading).toBe(false)
    })

    it('should handle empty library', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: [] })

      const store = useUserBookStore()
      const result = await store.getUserBooks()

      expect(result).toEqual([])
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when fetching library fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Unauthorized' } }
      })

      const store = useUserBookStore()
      const userStore = useUserStore()

      const result = await store.getUserBooks()

      expect(result).toEqual([])
      expect(userStore.me.books).toEqual([])
      expect(store._isLoading).toBe(false)
    })
  })

  describe('getUserBook', () => {
    it('should fetch specific book from library', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockBook })

      const store = useUserBookStore()
      const result = await store.getUserBook('B-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/user/books/B-TEST-1234')
      expect(result).toEqual(mockBook)
      expect(store._book).toEqual(mockBook)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when fetching book fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Book not found' } }
      })

      const store = useUserBookStore()

      await expect(store.getUserBook('B-INVALID')).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('postUserBooks', () => {
    it('should add book to library with internal ID', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: { book: mockBook } })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [] } as any

      const result = await store.postUserBooks(mockBook)

      expect(mockAxios.post).toHaveBeenCalledWith(
        '/user/books',
        expect.objectContaining({
          book_id: 'B-TEST-1234',
          is_private: false,
          reading_status: 'read'
        })
      )
      expect(result).toBe(true)
      expect(userStore.me.books).toContainEqual(mockBook)
      expect(store._isLoading).toBe(false)
    })

    it('should add book with Google ID', async () => {
      const googleBook = {
        id: 'google-book-id',
        title: 'Google Book',
        authors: 'Google Author',
        google_id: 'google-book-id'
      }
      mockAxios.post.mockResolvedValueOnce({ data: { book: googleBook } })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [] } as any

      await store.postUserBooks(googleBook as Book)

      expect(mockAxios.post).toHaveBeenCalledWith(
        '/user/books',
        expect.objectContaining({
          google_id: 'google-book-id'
        })
      )
    })

    it('should return false if book already in library', async () => {
      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      const result = await store.postUserBooks(mockBook)

      expect(result).toBe(false)
      expect(mockAxios.post).not.toHaveBeenCalled()
    })

    it('should add book as private', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: { book: mockBook } })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [] } as any

      await store.postUserBooks(mockBook, true, 'reading')

      expect(mockAxios.post).toHaveBeenCalledWith(
        '/user/books',
        expect.objectContaining({
          is_private: true,
          reading_status: 'reading'
        })
      )
    })

    it('should handle error when adding book fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Error adding book' } }
      })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [] } as any

      await expect(store.postUserBooks(mockBook)).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('patchUserBook', () => {
    it('should update reading status', async () => {
      mockAxios.patch.mockResolvedValueOnce({ data: {} })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await store.patchUserBook('B-TEST-1234', { reading_status: 'reading' })

      expect(mockAxios.patch).toHaveBeenCalledWith('/user/books/B-TEST-1234', {
        reading_status: 'reading'
      })
      expect(store._isLoading).toBe(false)
    })

    it('should update read date', async () => {
      mockAxios.patch.mockResolvedValueOnce({ data: {} })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await store.patchUserBook('B-TEST-1234', { read_at: '2024-06-01' })

      expect(mockAxios.patch).toHaveBeenCalledWith('/user/books/B-TEST-1234', {
        read_at: '2024-06-01'
      })
    })

    it('should update privacy setting', async () => {
      mockAxios.patch.mockResolvedValueOnce({ data: {} })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await store.patchUserBook('B-TEST-1234', { is_private: true })

      expect(mockAxios.patch).toHaveBeenCalledWith('/user/books/B-TEST-1234', {
        is_private: true
      })
    })

    it('should update multiple fields at once', async () => {
      mockAxios.patch.mockResolvedValueOnce({ data: {} })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await store.patchUserBook('B-TEST-1234', {
        reading_status: 'read',
        read_at: '2024-06-01'
      })

      expect(mockAxios.patch).toHaveBeenCalledWith('/user/books/B-TEST-1234', {
        reading_status: 'read',
        read_at: '2024-06-01'
      })
    })

    it('should handle error when updating fails', async () => {
      mockAxios.patch.mockRejectedValueOnce({
        response: { data: { message: 'Update failed' } }
      })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await expect(store.patchUserBook('B-TEST-1234', { reading_status: 'invalid' as any })).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('deleteUserBook', () => {
    it('should remove book from library', async () => {
      mockAxios.delete.mockResolvedValueOnce({ data: {} })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: mockBooks } as any

      const result = await store.deleteUserBook('B-TEST-1234')

      expect(mockAxios.delete).toHaveBeenCalledWith('/user/books/B-TEST-1234')
      expect(result).toBe(true)
      expect(userStore.me.books?.find((b) => b.id === 'B-TEST-1234')).toBeUndefined()
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when removing book fails', async () => {
      mockAxios.delete.mockRejectedValueOnce({
        response: { data: { message: 'Delete failed' } }
      })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await expect(store.deleteUserBook('B-TEST-1234')).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('replaceUserBook', () => {
    it('should replace book with new edition by ID', async () => {
      const newBook = { ...mockBook, id: 'B-NEW-EDITION' }
      mockAxios.put.mockResolvedValueOnce({ data: { book: newBook } })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      const result = await store.replaceUserBook('B-TEST-1234', 'B-NEW-EDITION')

      expect(mockAxios.put).toHaveBeenCalledWith('/user/books/B-TEST-1234/replace', {
        new_book_id: 'B-NEW-EDITION'
      })
      expect(result).toEqual(newBook)
      expect(store._isLoading).toBe(false)
    })

    it('should replace book with Amazon edition', async () => {
      const amazonBook = {
        id: 'B-AMAZON-123',
        title: 'Amazon Edition',
        authors: 'Test Author',
        amazon_asin: 'B00ASIN123',
        thumbnail: 'https://amazon.com/cover.jpg'
      }
      mockAxios.put.mockResolvedValueOnce({ data: { book: amazonBook } })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await store.replaceUserBook('B-TEST-1234', amazonBook as Book)

      expect(mockAxios.put).toHaveBeenCalledWith(
        '/user/books/B-TEST-1234/replace',
        expect.objectContaining({
          amazon_asin: 'B00ASIN123',
          title: 'Amazon Edition'
        })
      )
    })

    it('should handle error when replacing fails', async () => {
      mockAxios.put.mockRejectedValueOnce({
        response: { data: { message: 'Replace failed' } }
      })

      const store = useUserBookStore()
      const userStore = useUserStore()
      userStore._me = { id: 'U-TEST-1234', books: [mockBook] } as any

      await expect(store.replaceUserBook('B-TEST-1234', 'B-INVALID')).rejects.toThrow()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('getUserBookFromUser', () => {
    it('should fetch book from another user', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockBook })

      const store = useUserBookStore()
      const result = await store.getUserBookFromUser('otheruser', 'B-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/users/otheruser/books/B-TEST-1234')
      expect(result).toEqual(mockBook)
      expect(store._isLoading).toBe(false)
    })
  })
})
