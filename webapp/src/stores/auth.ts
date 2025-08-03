import type { AuthResponse, User } from '@/models'
import router from '@/router'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { LocalStorage, Notify } from 'quasar'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    _isLoading: false,
    _isGoogleLoading: false,
    _user: {} as User
  }),

  persist: true,

  getters: {
    isAuthenticated: (state) => Boolean(state._user.id),
    isLoading: (state) => state._isLoading,
    isGoogleLoading: (state) => state._isGoogleLoading,
    user: (state) => state._user
  },
  actions: {
    setUser(user: User) {
      this.$patch({ _user: user })
    },

    async getAuthMe() {
      this._isLoading = true
      return await api
        .get('/auth/me')
        .then((response) => {
          this.$patch({ _user: response.data })

          if (this.isAuthenticated) {
            router.push('/')
          }
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async postAuthLogin(email: string, password: string) {
      this._isLoading = true
      return await api
        .post('/auth/login', { email, password })
        .then((response) => {
          const authData: AuthResponse = response.data

          localStorage.setItem('auth_token', authData.access_token)
          LocalStorage.set('user', authData.user)

          this.$patch({ _user: authData.user })

          router.push('/')

          return authData
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async postAuthRegister(data: { display_name: string; email: string; username: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      return await api
        .post('/auth/register', data)
        .then((response) => {
          const authData: AuthResponse = response.data

          localStorage.setItem('auth_token', authData.access_token)
          LocalStorage.set('user', authData.user)

          this.$patch({ _user: authData.user })

          router.push('/')

          return authData
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async postAuthLogout() {
      this._isLoading = true
      return await api.post('/auth/logout').finally(() => {
        this._isLoading = false
        this.$reset()
        LocalStorage.clear()
        router.push('/login')
      })
    },

    async putPassword(data: { current_password: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      return await api
        .put('/password', data)
        .then((response) => response.data)
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async postForgotPassword(email: string) {
      return await api
        .post('/auth/forgot-password', { email })
        .then((response) => response.data)
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
    },

    async postResetPassword(data: { token: string; email: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      return await api
        .post('/auth/reset-password', data)
        .then((response) => response.data)
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async postGoogleSignIn(idToken: string) {
      this._isGoogleLoading = true
      return await api
        .post('/auth/google', { id_token: idToken })
        .then((response) => {
          const authData: AuthResponse = response.data

          localStorage.setItem('auth_token', authData.access_token)
          LocalStorage.set('user', authData.user)

          this.$patch({ _user: authData.user })
          Notify.create({ message: 'Login with Google successful!', type: 'positive' })
          router.push('/')

          return authData
        })
        .catch((error) => {
          Notify.create({ message: error.response.data.message || 'Google Sign In failed', type: 'negative' })
          throw error
        })
        .finally(() => (this._isGoogleLoading = false))
    },

    isAuthenticatedCheck(): boolean {
      return !!localStorage.getItem('auth_token')
    },

    restoreSession(): boolean {
      const token = localStorage.getItem('auth_token')
      const userData = LocalStorage.getItem('user')

      if (token && userData && typeof userData === 'object') {
        this.$patch({ _user: userData as User })
        return true
      }

      return false
    }
  }
})
