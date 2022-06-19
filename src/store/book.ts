import { db } from '@/firebase'
import { Book, User } from '@/models'
import { useUserStore } from '@/store'
import { collection, deleteDoc, doc, getDocs, runTransaction, setDoc } from 'firebase/firestore'
import { defineStore } from 'pinia'
import { LocalStorage } from 'quasar'

function throwError(error: { code: string }) {
  console.error(error)
  throw error.code
}

export const useBookStore = defineStore('book', {
  state: () => ({
    _books: (LocalStorage.getItem('books') || []) as Book[]
  }),

  getters: {
    getBooks: (state) => state._books,
    getBook: (state) => (id: string) => state._books.find((book) => book.id === id),
    getUserUid() {
      const userStore = useUserStore()
      return userStore.getUser?.uid
    }
  },

  actions: {
    async fetchBooks() {
      await getDocs(collection(db, 'users', this.getUserUid, 'books'))
        .then((querySnapshot) => {
          const books = querySnapshot.docs.map((docBook) => docBook.data())
          this._books = []
          this.$patch({ _books: books })
          return books
        })
        .catch(throwError)
      if (this.getBooks) {
        LocalStorage.set('books', this._books)
      }
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
