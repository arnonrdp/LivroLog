import { auth, db } from '@/firebase'
import { useUserStore } from '@/store'
import { createUserWithEmailAndPassword, sendPasswordResetEmail, type UserInfo } from '@firebase/auth'
import { doc, setDoc } from '@firebase/firestore'
import { defineStore } from 'pinia'

function throwError(error: { code: string }) {
  throw error.code
}

export const useRegisterStore = defineStore('register', {
  actions: {
    async fetchProfile(user: UserInfo) {
      const userStore = useUserStore()
      userStore.fetchUserProfile(user)
    },

    async signup(name: string, email: string, password: string) {
      await createUserWithEmailAndPassword(auth, email, password)
        .then(async (userCredential) => {
          const username = email.split('@')[1] === 'gmail.com' ? email.split('@')[0] : email.split('@')[0] + new Date().getTime()
          await setDoc(doc(db, 'users', userCredential.user.uid), { name, username, email, password })
            .then(() => this.fetchProfile(userCredential.user))
            .catch(throwError)
        })
        .catch(throwError)
    },

    async resetPassword(email: string) {
      await sendPasswordResetEmail(auth, email)
        .then((result) => result)
        .catch(throwError)
    }
  }
})
