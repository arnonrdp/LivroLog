import { i18n } from '@/locales'
import type { AuthResponse, User } from '@/models'
import router from '@/router'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { LocalStorage, Notify } from 'quasar'
import { useUserStore } from './user'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    _isLoading: false,
    _isGoogleLoading: false
  }),

  persist: true,

  getters: {
    isAuthenticated: () => {
      const userStore = useUserStore()
      return Boolean(userStore.me.id)
    },
    isLoading: (state) => state._isLoading,
    isGoogleLoading: (state) => state._isGoogleLoading
  },
  actions: {

    setUser(user: User) {
      const userStore = useUserStore()
      userStore.setMe(user)
    },

    async getMe() {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .get('/auth/me')
        .then((response) => {
          userStore.setMe(response.data)
          if (this.isAuthenticated) {
            router.push('/')
          }
          return response.data
        })
        .catch((error) => {
          userStore.setMe({} as User)
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async postAuthLogin(email: string, password: string) {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .post('/auth/login', { email, password })
        .then((response) => {
          const authData: AuthResponse = response.data
          localStorage.setItem('auth_token', authData.access_token)
          LocalStorage.set('user', authData.user)
          userStore.setMe(authData.user)
          router.push('/')
          return authData
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async postAuthRegister(data: { display_name: string; email: string; username: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .post('/auth/register', data)
        .then((response) => {
          const authData: AuthResponse = response.data
          localStorage.setItem('auth_token', authData.access_token)
          LocalStorage.set('user', authData.user)
          userStore.setMe(authData.user)
          router.push('/')
          return authData
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async postAuthLogout() {
      this._isLoading = true
      try {
        await api.post('/auth/logout')
      } catch (error) {
        console.error('Logout API error:', error)
      }
      this.$reset()
      localStorage.removeItem('auth_token')
      LocalStorage.clear()
      localStorage.removeItem('auth')
      router.push('/login')
      this._isLoading = false
    },

    async putAuthPassword(data: { current_password: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      return await api
        .put('/auth/password', data)
        .then((response) => response.data)
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async postAuthForgotPassword(email: string) {
      this._isLoading = true
      return await api
        .post('/auth/forgot-password', { email })
        .then((response) => response.data)
        .catch((error) => {
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async postAuthResetPassword(data: { token: string; email: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      return await api
        .post('/auth/reset-password', data)
        .then((response) => {
          Notify.create({ message: i18n.global.t('password-reset-success'), type: 'positive' })
          return response.data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async postAuthGoogle(idToken: string) {
      this._isGoogleLoading = true
      const userStore = useUserStore()
      return await api
        .post('/auth/google', { id_token: idToken })
        .then((response) => {
          const authData: AuthResponse = response.data
          localStorage.setItem('auth_token', authData.access_token)
          LocalStorage.set('user', authData.user)
          userStore.setMe(authData.user)
          Notify.create({ message: 'Login with Google successful!', type: 'positive' })
          router.push('/')
          return authData
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
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
      const userStore = useUserStore()

      if (token && userData && typeof userData === 'object') {
        userStore.setMe(userData as User)
        return true
      }

      return false
    },

    async refreshUser() {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .get('/auth/me')
        .then((response) => {
          userStore.setMe(response.data)
          LocalStorage.set('user', response.data)
          return response.data
        })
        .catch((error) => {
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async putMe(data: { display_name?: string; username?: string; email?: string; shelf_name?: string; locale?: string; is_private?: boolean }) {
      this._isLoading = true
      return await api
        .put('/auth/me', data)
        .then((response) => {
          this.setUser(response.data.user)
          Notify.create({ message: i18n.global.t('profile-updated'), type: 'positive' })
          return response.data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async deleteMe() {
      this._isLoading = true
      return await api
        .delete('/auth/me')
        .then(() => {
          router.push('/login')
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    }
  }
})
