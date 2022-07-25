import { auth, db } from '@/firebase'
import router from '@/router'
import { useUserStore } from '@/store'
import {
  getAdditionalUserInfo,
  GoogleAuthProvider,
  signInWithEmailAndPassword,
  signInWithPopup,
  signOut,
  type UserInfo
} from 'firebase/auth'
import { doc, setDoc } from 'firebase/firestore'
import { defineStore } from 'pinia'
import { LocalStorage } from 'quasar'

function throwError(error: { code: string }) {
  throw error.code
}

export const useAuthStore = defineStore('auth', {
  actions: {
    async fetchProfile(user: UserInfo) {
      const userStore = useUserStore()
      userStore.fetchUserProfile(user)
    },

    async googleSignIn() {
      const provider = new GoogleAuthProvider()
      await signInWithPopup(auth, provider)
        .then(async (result) => {
          const isNewUser = getAdditionalUserInfo(result)?.isNewUser
          const { email, displayName, photoURL, uid } = result.user
          if (isNewUser) {
            // TODO: improve this
            const username = email?.split('@')[0]
            await setDoc(doc(db, 'users', uid), { email, displayName, photoURL, username })
          }
          this.fetchProfile(result.user)
        })
        .catch(throwError)
    },

    async login(email: string, password: string) {
      await signInWithEmailAndPassword(auth, email, password)
        .then((userCredential) => this.fetchProfile(userCredential.user))
        .catch(throwError)
    },

    logout() {
      const userStore = useUserStore()
      signOut(auth).then(() => {
        userStore.$reset()
        LocalStorage.remove('user')
        router.push('/login')
      })
    }
  }
})
