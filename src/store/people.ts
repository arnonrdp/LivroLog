import { db } from '@/firebase'
import type { User } from '@/models'
import { arrayUnion, arrayRemove, collection, doc, getDocs, query, updateDoc, where } from '@firebase/firestore'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { useUserStore } from './user'

function throwError(error: { code: string }) {
  throw error.code
}

export const usePeopleStore = defineStore('people', {
  state: () => ({
    _isLoading: false,
    _people: [] as User[],
    _person: {} as User
  }),

  persist: true,

  getters: {
    isLoading: (state) => state._isLoading,
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
    },

    async addFollower(uid: User['uid']) {
      const userStore = useUserStore()
      const personRef = doc(db, 'users', uid)

      this._isLoading = true
      await updateDoc(personRef, { followers: arrayUnion(userStore.getUserRef) })
        .then(() => {
          console.log('userStore.getUserRef', userStore.getUserRef)

          this._person.followers ??= []
          if (!this._person.followers.some((obj) => obj.id === userStore.getUserRef.id)) {
            this._person.followers.push(userStore.getUserRef)
          }
        })
        .catch(throwError)

      await updateDoc(userStore.getUserRef, { following: arrayUnion(personRef) })
        .then(() => {
          console.log('personRef', personRef)

          userStore._user.following ??= []
          if (!userStore._user.following.some((obj) => obj.id === personRef.id)) {
            userStore._user.following.push(personRef)
          }
        })
        .catch(throwError)
      this._isLoading = false
    },

    async removeFollower(uid: User['uid']) {
      const userStore = useUserStore()
      const personRef = doc(db, 'users', uid)

      this._isLoading = true
      await updateDoc(personRef, { followers: arrayRemove(userStore.getUserRef) })
        .then(() => {
          this._person.followers = this._person.followers?.filter((follower) => follower.id !== userStore.getUser.uid)
        })
        .catch(throwError)

      await updateDoc(userStore.getUserRef, { following: arrayRemove(personRef) })
        .then(() => {
          userStore._user.following = userStore._user.following?.filter((following) => following.id !== uid)
        })
        .catch(throwError)
      this._isLoading = false
    }
  }
})
