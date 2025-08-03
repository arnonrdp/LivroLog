import { i18n } from '@/locales'
import type { Book } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useBookStore = defineStore('book', {
  state: () => ({
    _books: [] as Book[],
    _isLoading: false,
    _searchResults: [] as Book[]
  }),

  persist: true,

  getters: {
    books: (state) => state._books,
    isLoading: (state) => state._isLoading,
    searchResults: (state) => state._searchResults
  },

  actions: {
    async getBooks() {
      this._isLoading = true
      return await api
        .get('/books')
        .then((response) => {
          const books = response.data.data || response.data

          // Sort by reading date (most recent first)
          books.sort((a: Book, b: Book) => {
            const aDate = a.pivot?.read_at || a.readIn
            const bDate = b.pivot?.read_at || b.readIn
            if (!aDate || !bDate) return 0
            return new Date(bDate).getTime() - new Date(aDate).getTime()
          })

          this.$patch({ _books: books })
          return books
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async getBooksSearch(search: string) {
      this._isLoading = true
      return await api
        .get(`/books/search?q=${encodeURIComponent(search)}`)
        .then((response) => {
          this.$patch({ _searchResults: response.data })
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async postBook(payload: object) {
      this._isLoading = true
      await api
        .post('/books', payload)
        .then(async () => {
          Notify.create({ message: i18n.global.t('book.added-to-shelf'), color: 'positive' })
          await this.getBooks()
        })
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
        .catch((error) => Notify.create({ message: error.response?.data?.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async deleteBook(bookId: Book['id']) {
      this._isLoading = true
      await api
        .delete(`/books/${bookId}`)
        .then(() => {
          this._books = this._books.filter((book) => book.id !== bookId)
          Notify.create({ icon: 'check_circle', message: i18n.global.t('book.removed-success') })
        })
        .catch(() => Notify.create({ icon: 'error', message: i18n.global.t('book.removed-error') }))
        .finally(() => (this._isLoading = false))
    },

    async patchBooksReadDates(books: Partial<Book>[]) {
      this._isLoading = true
      return api
        .patch('/books/read-dates', { books })
        .then((response) => {
          this._books = this._books.map((book) => {
            const updated = books.find((b) => b.id === book.id)
            return updated ? { ...book, readIn: updated.readIn } : book
          })
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response?.data?.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    }
  }
})
