import type { Book } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

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

    async getBooksSearch(search: string) {
      this._isLoading = true
      return await api
        .get(`/books/search?q=${encodeURIComponent(search)}`)
        .then((response) => {
          const books = response.data.books || response.data || []
          this.$patch({ _searchResults: books })
          return books
        })
        .catch((error) => Notify.create({ message: error.response.data.message, type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async postBook(payload: object) {
      this._isLoading = true
      await api
        .post('/books', payload)
        .then(async () => {
          Notify.create({ message: i18n.global.t('added-to-shelf'), type: 'positive' })
          await this.getBooks()
        })
        .catch((error) => Notify.create({ message: error.response.data.message, type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async putBook(bookId: Book['id'], payload: object) {
      this._isLoading = true
      return api
        .put(`/books/${bookId}`, payload)
        .then((response) => {
          this._books = this._books.map((book) => (book.id === bookId ? { ...book, ...payload } : book))
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response?.data?.message, type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async deleteBook(bookId: Book['id']) {
      this._isLoading = true
      await api
        .delete(`/books/${bookId}`)
        .then(() => {
          this._books = this._books.filter((book) => book.id !== bookId)
          Notify.create({ message: i18n.global.t('removed-success'), type: 'positive' })
        })
        .catch(() => Notify.create({ message: i18n.global.t('removed-error'), type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    // User's library methods (authenticated user only)
    async getUserBooks() {
      this._isLoading = true
      return await api
        .get('/user/books')
        .then((response) => {
          const books = response.data || []
          this.$patch({ _books: books })
          return books
        })
        .catch((error) => Notify.create({ message: error.response?.data?.message, type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async removeBookFromLibrary(bookId: Book['id']) {
      this._isLoading = true
      await api
        .delete(`/user/books/${bookId}`)
        .then(() => {
          this._books = this._books.filter((book) => book.id !== bookId)
          Notify.create({ message: i18n.global.t('removed-success'), type: 'positive' })
        })
        .catch(() => Notify.create({ message: i18n.global.t('removed-error'), type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async updateBookReadDate(bookId: Book['id'], readDate: string) {
      this._isLoading = true
      return api
        .patch(`/user/books/${bookId}/read-date`, {
          read_at: readDate
        })
        .then((response) => {
          this._books = this._books.map((book) =>
            book.id === bookId ? { ...book, pivot: { ...book.pivot, read_at: readDate } as typeof book.pivot } : book
          )
          Notify.create({ message: i18n.global.t('read-date-saved'), type: 'positive' })
          return response.data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-occurred'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    }
  }
})
