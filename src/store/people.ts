import { db } from '@/firebase'
import type { User } from '@/models'
import { collection, getDocs, query, where } from '@firebase/firestore'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'

function throwError(error: { code: string }) {
  throw error.code
}

export const usePeopleStore = defineStore('people', {
  state: () => ({
    _people: [] as User[],
    _person: {} as User
  }),

  getters: {
    getPeople: (state) => state._people,
    getPerson: (state) => state._person,
    getRouteUsername() {
      const route = useRoute()
      return route.params.username as User['username']
    }
  },

  actions: {
    async fetchPeople() {
      await getDocs(collection(db, 'users'))
        .then((querySnapshot) => {
          this._people = querySnapshot.docs.map((doc) => ({ uid: doc.id, ...doc.data() })) as User[]
        })
        .catch(throwError)
    },

    async fetchPersonAndBooks() {
      await getDocs(query(collection(db, 'users'), where('username', '==', this.getRouteUsername.toLowerCase())))
        .then((querySnapshot) => (this._person = { uid: querySnapshot.docs[0].id, ...querySnapshot.docs[0].data() } as User))
        .catch(throwError)

      await getDocs(collection(db, 'users', this._person.uid, 'books'))
        .then((querySnapshot) => (this._person.books = querySnapshot.docs.map((doc) => doc.data()) as User['books']))
        .catch(throwError)
    }
  }
})
