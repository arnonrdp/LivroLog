import { auth, db } from '@/firebase'
import type { User } from '@/models'
import router from '@/router'
import { EmailAuthProvider, reauthenticateWithCredential, updateEmail, updatePassword, type UserInfo } from '@firebase/auth'
import { collection, doc, getDoc, getDocs, runTransaction, updateDoc } from '@firebase/firestore'
import { defineStore } from 'pinia'
import { LocalStorage } from 'quasar'

function throwError(error: { code: string }) {
  throw error.code
}

export const useUserStore = defineStore('user', {
  state: () => ({
    _user: (LocalStorage.getItem('user') || {}) as User
  }),

  getters: {
    getUser: (state) => state._user,
    isAuthenticated: (state) => !!state._user?.uid
  },

  actions: {
    async fetchUserProfile(user: UserInfo) {
      await getDoc(doc(db, 'users', user.uid))
        .then((document) =>
          this.$patch({
            _user: { uid: document.id, ...document.data() }
          })
        )
        .catch(throwError)

      if (this.getUser) {
        LocalStorage.set('user', this._user)
        router.push('/')
      }
    },

    async checkEmail(email: User['email']) {
      const users = await getDocs(collection(db, 'users'))

      const emails = users.docs.map((document) => document.data().email)

      return Boolean(emails.includes(email))
    },

    async checkUsername(username: User['username']) {
      const users = await getDocs(collection(db, 'users'))

      const usernames = users.docs.map((document) => document.data().username.toLowerCase())

      return Boolean(usernames.includes(username.toLowerCase()))
    },

    async updateAccount(credentials: { [key: string]: string }) {
      const user = auth.currentUser
      const credential = EmailAuthProvider.credential(this.getUser.email, credentials.password)

      if (!user) return

      await reauthenticateWithCredential(user, credential)
        .then(async () => {
          await updateDoc(doc(db, 'users', this.getUser.uid), { email: credentials.email })
          updateEmail(user, credentials.email)
            .then(() => {
              this.$patch({ _user: { email: credentials.email } })
              LocalStorage.set('user', this._user)
            })
            .catch(throwError)
          updatePassword(user, credentials.newPass)
        })
        .catch(throwError)
    },

    async updateProfile(user: Pick<User, 'displayName' | 'username'>) {
      await runTransaction(db, async (transaction) => {
        transaction.update(doc(db, 'users', this.getUser.uid), { ...user })
      })
        .then(() => {
          this._user.displayName = user.displayName
          this._user.username = user.username
        })
        .catch(throwError)
    }
  }
})
