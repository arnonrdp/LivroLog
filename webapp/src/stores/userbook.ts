import { i18n } from '@/locales'
import type { Book, ReadingStatus } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'
import { useUserStore } from './user'

export const useUserBookStore = defineStore('userbook', {
  state: () => ({
    _isLoading: false
  }),

  persist: true,

  getters: {
    isLoading: (state) => state._isLoading
  },

  actions: {
    async getUserBooks() {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .get('/user/books')
        .then((response) => {
          // Handle different response formats
          const books = response.data.data || response.data.books || response.data || []
          const validBooks = Array.isArray(books) ? books : []

          // Update userStore.me.books
          userStore.updateMe({ books: validBooks })
          return validBooks
        })
        .catch((error) => {
          // Clear books on error
          userStore.updateMe({ books: [] })
          Notify.create({ message: error.response?.data?.message, type: 'negative' })
          return []
        })
        .finally(() => (this._isLoading = false))
    },

    async postUserBooks(book: Book, isPrivate: boolean = false, readingStatus: ReadingStatus = 'read') {
      this._isLoading = true
      const userStore = useUserStore()

      // Check if book is already in user's library BEFORE making API call
      const userBooks = userStore.me.books || []
      const isAlreadyInLibrary = userBooks.some((userBook) => {
        // Check by internal ID (if book.id exists and is internal)
        if (book.id && book.id.startsWith('B-') && userBook.id === book.id) {
          return true
        }
        // Check by google_id (most reliable for external books)
        if (book.google_id && userBook.google_id === book.google_id) {
          return true
        }
        // Legacy check: if book.id is actually a google_id (shouldn't happen now but keep for safety)
        if (book.id && !book.id.startsWith('B-') && userBook.google_id === book.id) {
          return true
        }
        return false
      })

      if (isAlreadyInLibrary) {
        this._isLoading = false
        return false
      }

      // Send only identifiers - backend handles the rest
      const bookData: { book_id?: string; isbn?: string; google_id?: string; is_private?: boolean; reading_status?: ReadingStatus } = {
        is_private: isPrivate,
        reading_status: readingStatus
      }

      // Check if book.id is an internal book ID (starts with 'B-') or external (Google ID)
      if (book.id && book.id.startsWith('B-')) {
        // Internal book - use book_id
        bookData.book_id = book.id
      } else if (book.id && !book.id.startsWith('B-')) {
        // External book - book.id is actually a google_id
        bookData.google_id = book.id
      } else if (book.isbn) {
        bookData.isbn = book.isbn
        if (book.google_id) {
          bookData.google_id = book.google_id
        }
      } else if (book.google_id) {
        bookData.google_id = book.google_id
      }

      return await api
        .post('/user/books', bookData)
        .then((response) => {
          // Use the book data from the response instead of making another GET request
          const addedBook = response.data.book
          if (addedBook) {
            const currentBooks = userStore.me.books || []
            const updatedBooks = [addedBook, ...currentBooks]
            userStore.updateMe({ books: updatedBooks })

            // Also update currentUser if it's the same user
            if (userStore.user.id === userStore.me.id) {
              userStore.$patch((state) => {
                state._user.books = updatedBooks
              })
            }
          }
          Notify.create({ message: i18n.global.t('book-added-to-library'), type: 'positive' })
          return true
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async patchUserBook(bookId: Book['id'], updates: { read_at?: string; is_private?: boolean; reading_status?: ReadingStatus }) {
      this._isLoading = true
      const userStore = useUserStore()
      return api
        .patch(`/user/books/${bookId}`, updates)
        .then((response) => {
          const currentBooks = userStore.me.books || []
          const updatedBooks = currentBooks.map((book) => {
            if (book.id === bookId && book.pivot) {
              const updatedPivot = { ...book.pivot }
              if (updates.read_at !== undefined) updatedPivot.read_at = updates.read_at
              if (updates.is_private !== undefined) updatedPivot.is_private = updates.is_private
              if (updates.reading_status !== undefined) updatedPivot.reading_status = updates.reading_status
              return { ...book, pivot: updatedPivot }
            }
            return book
          })
          userStore.updateMe({ books: updatedBooks })

          // Show appropriate success message
          if (Object.keys(updates).length > 1) {
            Notify.create({ message: i18n.global.t('book-updated'), type: 'positive' })
          } else if (updates.read_at !== undefined) {
            Notify.create({ message: i18n.global.t('read-date-saved'), type: 'positive' })
          } else if (updates.is_private !== undefined) {
            Notify.create({ message: i18n.global.t('privacy-updated'), type: 'positive' })
          } else if (updates.reading_status !== undefined) {
            Notify.create({ message: i18n.global.t('reading-status-updated'), type: 'positive' })
          }

          return response.data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    // Backward compatibility methods
    async patchUserBookReadDate(bookId: Book['id'], readDate: string) {
      return this.patchUserBook(bookId, { read_at: readDate })
    },

    async patchUserBookPrivacy(bookId: Book['id'], isPrivate: boolean) {
      return this.patchUserBook(bookId, { is_private: isPrivate })
    },

    async patchUserBookStatus(bookId: Book['id'], readingStatus: ReadingStatus) {
      return this.patchUserBook(bookId, { reading_status: readingStatus })
    },

    async deleteUserBook(bookId: Book['id']) {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .delete(`/user/books/${bookId}`)
        .then(() => {
          const currentBooks = userStore.me.books || []
          const updatedBooks = currentBooks.filter((book) => book.id !== bookId)
          userStore.updateMe({ books: updatedBooks })

          // Also update currentUser if it's the same user
          if (userStore.user.id === userStore.me.id) {
            userStore.$patch((state) => {
              state._user.books = updatedBooks
            })
          }

          Notify.create({ message: i18n.global.t('book-removed-from-library'), type: 'positive' })
          return true
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('removed-error'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    }
  }
})
