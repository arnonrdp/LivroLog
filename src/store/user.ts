import { auth, db } from '@/firebase'
import type { User } from '@/models'
import router from '@/router'
import { EmailAuthProvider, reauthenticateWithCredential, updateEmail, updatePassword, type UserInfo } from '@firebase/auth'
import { collection, doc, getDoc, getDocs, runTransaction, updateDoc } from '@firebase/firestore'
import { defineStore } from 'pinia'
import { usePeopleStore } from './people'

function throwError(error: { code: string }) {
  throw error.code
}

export const useUserStore = defineStore('user', {
  state: () => ({
    _isLoading: false,
    _user: {} as User
  }),

  persist: true,

  getters: {
    getUser: (state) => state._user,
    getUserRef: (state) => doc(db, 'users', state._user.uid),
    isAuthenticated: (state) => Boolean(state._user.uid),
    isFollowing: (state) => {
      const peopleStore = usePeopleStore()
      return state._user.following?.some((obj) => obj.id === peopleStore.getPerson.uid)
    },
    isLoading: (state) => state._isLoading
  },

  actions: {
    async fetchUserProfile(user: UserInfo) {
      this._isLoading = true
      await getDoc(doc(db, 'users', user.uid))
        .then((document) => {
          const user = { uid: document.id, ...document.data() } as User
          user.following = user.following?.map((obj) => ({ id: obj.id }))
          user.followers = user.followers?.map((obj) => ({ id: obj.id }))
          this.$patch({ _user: user })
        })
        .catch(throwError)
        .finally(() => (this._isLoading = false))

      if (this.isAuthenticated) {
        router.push('/')
      }
    },

    async checkEmail(email: User['email']) {
      this._isLoading = true
      const users = await getDocs(collection(db, 'users')).finally(() => (this._isLoading = false))

      const emails = users.docs.map((document) => document.data().email)

      return Boolean(emails.includes(email))
    },

    async checkUsername(username: User['username']) {
      this._isLoading
      const users = await getDocs(collection(db, 'users')).finally(() => (this._isLoading = false))

      const usernames = users.docs.map((document) => document.data().username.toLowerCase())

      return Boolean(usernames.includes(username.toLowerCase()))
    },

    async updateAccount(credentials: { [key: string]: string }) {
      const user = auth.currentUser
      const credential = EmailAuthProvider.credential(this.getUser.email, credentials.password)

      if (!user) return

      this._isLoading = true
      await reauthenticateWithCredential(user, credential)
        .then(async () => {
          await updateDoc(doc(db, 'users', this.getUser.uid), { email: credentials.email })
          updateEmail(user, credentials.email)
            .then(() => {
              this.$patch({ _user: this._user })
              this._user.email = credentials.email
            })
            .catch(throwError)
          updatePassword(user, credentials.newPass)
        })
        .catch(throwError)
        .finally(() => (this._isLoading = false))
    },

    async updateProfile(user: Pick<User, 'displayName' | 'username'>) {
      this._isLoading = true
      await runTransaction(db, async (transaction) => {
        transaction.update(doc(db, 'users', this.getUser.uid), { ...user })
      })
        .then(() => {
          this._user.displayName = user.displayName
          this._user.username = user.username
        })
        .catch(throwError)
        .finally(() => (this._isLoading = false))
    },

    async updateLocale(locale: User['locale']) {
      this._isLoading = true
      await runTransaction(db, async (transaction) => {
        transaction.update(doc(db, 'users', this.getUser.uid), { locale })
      })
        .then(() => (this._user.locale = locale))
        .catch(throwError)
        .finally(() => (this._isLoading = false))
    }
  }
})
