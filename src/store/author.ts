import { db } from '@/firebase'
import type { Book } from '@/models'
import { collection, getDocs } from 'firebase/firestore'
import { defineStore } from 'pinia'

function throwError(error: { code: string }) {
  console.error(error)
  throw error.code
}

export const useAuthorStore = defineStore('author', {
  state: () => ({
    _authors: [],
    _booksCollection: [] as Book[],
    _isLoading: false
  }),

  getters: {
    getAuthors: (state) => state._authors,
    getBooksCollection: (state) => state._booksCollection
  },

  actions: {
    async fetchBooksCollection() {
      this._isLoading = true
      await getDocs(collection(db, 'books'))
        .then((querySnapshot) => {
          const books = querySnapshot.docs.map((docBook) => docBook.data())
          this.$patch({ _booksCollection: books })
        })
        .catch(throwError)
        .finally(() => (this._isLoading = false))
    },

    countOccurrences: (items: string[]) => {
      return items.reduce((count, item) => {
        return { ...count, [item]: (count[item] || 0) + 1 }
      }, {} as Record<string, number>)
    },

    authorsAndQuantities() {
      const authors = this.getBooksCollection.flatMap((book) => book.authors || [])
      return Object.entries(this.countOccurrences(authors)).map(([author, count]) => ({ author, count }))
    },

    booksAndQuantities() {
      const titles = this.getBooksCollection.map((book) => book.title || '')
      return Object.entries(this.countOccurrences(titles)).map(([book, count]) => ({ book, count }))
    }
  }
})
