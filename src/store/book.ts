import { db } from '@/firebase'
import type { Book, GoogleBook, User } from '@/models'
import { useUserStore } from '@/store'
import axios from 'axios'
import { collection, deleteDoc, doc, getDocs, runTransaction, setDoc } from 'firebase/firestore'
import { defineStore } from 'pinia'
import { LocalStorage } from 'quasar'

function throwError(error: { code: string }) {
  console.error(error)
  throw error.code
}

function sortBooksByReadDate(books: Book[]) {
  return books.sort((a: Book, b: Book) => {
    if (!a.readIn || !b.readIn) return 0
    if (a.readIn < b.readIn) return 1
    if (a.readIn > b.readIn) return -1
    return 0
  })
}

export const useBookStore = defineStore('book', {
  state: () => ({
    _books: (LocalStorage.getItem('books') || []) as Book[],
    _isLoading: false,
    _searchResults: [] as Book[]
  }),

  getters: {
    getBooks: (state) => state._books,
    getBook: (state) => (id: string) => state._books.find((book) => book.id === id),
    getUserUid() {
      const userStore = useUserStore()
      return userStore.getUser?.uid
    },
    getSearchResults: (state) => state._searchResults,
    isLoading: (state) => state._isLoading
  },

  actions: {
    async fetchBooks() {
      await getDocs(collection(db, 'users', this.getUserUid, 'books'))
        .then((querySnapshot) => {
          const books = querySnapshot.docs.map((docBook) => docBook.data())
          sortBooksByReadDate(books as Book[])
          this._books = []
          this.$patch({ _books: books })
          return books
        })
        .catch(throwError)
      if (this.getBooks) {
        LocalStorage.set('books', this._books)
      }
    },

    async searchBookOnGoogle(search: string) {
      const books = [] as Book[]

      this._isLoading = true
      await axios
        .get(`https://www.googleapis.com/books/v1/volumes?q=${search}&maxResults=40&printType=books`)
        .then((response) => {
          response.data.items.map((item: GoogleBook) =>
            books.push({
              id: item.id,
              title: item.volumeInfo.title || '',
              authors: item.volumeInfo.authors || [],
              ISBN: item.volumeInfo.industryIdentifiers?.[0].identifier || item.id,
              thumbnail: item.volumeInfo.imageLinks?.thumbnail.replace('http', 'https') || null
            })
          )
          this.$patch({ _searchResults: books })
        })
        .catch(throwError)
        .finally(() => (this._isLoading = false))
    },

    async addBook(book: Book, userUid: User['uid']) {
      if (this.getBooks.some((document) => document.id === book.id)) {
        throwError({ code: 'book_already_exists' })
      }

      await setDoc(doc(db, 'users', userUid, 'books', book.id), book)
        .then(() => {
          this.$patch({ _books: [...this.getBooks, book] })
          LocalStorage.set('books', this._books)
        })
        .catch(throwError)
    },

    async updateReadDates(books: Pick<Book, 'id' | 'readIn'>[]) {
      await runTransaction(db, async (transaction) => {
        for (const book of books) {
          transaction.update(doc(db, 'users', this.getUserUid, 'books', book.id), { readIn: book.readIn })
        }
      })
        .then(() => {
          for (const book of books) {
            const index = this._books.findIndex((userBook) => userBook.id === book.id)
            this._books[index].readIn = book.readIn
          }
          LocalStorage.set('books', this._books)
        })
        .catch(throwError)
    },

    async removeBook(id: Book['id']) {
      await deleteDoc(doc(db, 'users', this.getUserUid, 'books', id))
        .then(() => {
          const index = this._books.findIndex((book) => book.id === id)
          this._books.splice(index, 1)
          LocalStorage.set('books', this._books)
        })
        .catch(throwError)
    }
  }
})
