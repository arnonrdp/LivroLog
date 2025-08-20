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
    }
  }
})
