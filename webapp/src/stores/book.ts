import { i18n } from '@/locales'
import type { Book } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'
import { useUserStore } from './user'

export const useBookStore = defineStore('book', {
  state: () => ({
    _book: null as Book | null,
    _books: [] as Book[],
    _isLoading: false
  }),

  persist: true,

  getters: {
    book: (state) => state._book,
    books: (state) => state._books,
    isLoading: (state) => state._isLoading
  },

  actions: {
    async getBooks(params: { search?: string; showcase?: boolean; all?: boolean } = {}) {
      this._isLoading = true
      return await api
        .get('/books', { params })
        .then((response) => {
          const books = response.data.books || response.data.data || response.data || []

          if (params.search) {
            return books
          }

          books.sort((a: Book, b: Book) => {
            const aDate = a.pivot?.read_at || a.readIn
            const bDate = b.pivot?.read_at || b.readIn
            if (!aDate || !bDate) return 0
            return new Date(bDate).getTime() - new Date(aDate).getTime()
          })

          this.$patch({ _books: books })
          return books
        })
        .catch((error) => Notify.create({ message: error.response.data.message, type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async getUserBooks() {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .get('/user/books', { params: { all: 'true' } })
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

    async postUserBooks(book: Book) {
      this._isLoading = true
      const userStore = useUserStore()

      // Send only identifiers - backend handles the rest
      const bookData: { book_id?: string; isbn?: string; google_id?: string } = {}

      if (book.id) {
        bookData.book_id = book.id
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
            if (userStore.currentUser.id === userStore.me.id) {
              userStore.$patch((state) => {
                state._currentUser.books = updatedBooks
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
          if (userStore.currentUser.id === userStore.me.id) {
            userStore.$patch((state) => {
              state._currentUser.books = updatedBooks
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
    },

    async patchUserBookReadDate(bookId: Book['id'], readDate: string) {
      this._isLoading = true
      const userStore = useUserStore()
      return api
        .patch(`/user/books/${bookId}/read-date`, {
          read_at: readDate
        })
        .then((response) => {
          const currentBooks = userStore.me.books || []
          const updatedBooks = currentBooks.map((book) =>
            book.id === bookId && book.pivot ? { ...book, pivot: { ...book.pivot, read_at: readDate } } : book
          )
          userStore.updateMe({ books: updatedBooks })
          Notify.create({ message: i18n.global.t('read-date-saved'), type: 'positive' })
          return response.data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    setCurrentBook(book: Book | null) {
      this._book = book
    },

    clearCurrentBook() {
      this._book = null
    }
  }
})
