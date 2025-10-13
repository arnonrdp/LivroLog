import { i18n, type SupportedLocale } from '@/locales'
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
      const hasToken = Boolean(LocalStorage.getItem('access_token'))
      return Boolean(userStore.me.id) && hasToken
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

      // Check for email verification status in URL
      const urlParams = new URLSearchParams(window.location.search)
      const status = urlParams.get('status')
      const error = urlParams.get('error')

      return await api
        .get('/auth/me')
        .then((response) => {
          userStore.setMe(response.data)
          LocalStorage.set('user', response.data)

          // Handle email verification notifications
          if (status === 'verified') {
            Notify.create({ message: i18n.global.t('email-verified-successfully'), type: 'positive' })
            window.history.replaceState({}, '', window.location.pathname)
          } else if (status === 'already_verified') {
            Notify.create({ message: i18n.global.t('email-already-verified'), type: 'info' })
            window.history.replaceState({}, '', window.location.pathname)
          } else if (error === 'invalid_link' || error === 'invalid_hash') {
            Notify.create({ message: i18n.global.t('invalid-verification-link'), type: 'negative' })
            window.history.replaceState({}, '', window.location.pathname)
          } else if (error === 'user_not_found') {
            Notify.create({ message: i18n.global.t('user-not-found', 'User not found. Please contact support.'), type: 'negative' })
            window.history.replaceState({}, '', window.location.pathname)
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

    async putMe(data: { display_name?: string; username?: string; email?: string; shelf_name?: string; locale?: string; is_private?: boolean }) {
      this._isLoading = true
      return await api
        .put('/auth/me', data)
        .then((response) => {
          this.setUser(response.data.user)
          LocalStorage.set('user', response.data.user)
          if (data.locale) {
            i18n.global.locale.value = data.locale as SupportedLocale
          }
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
        .then(() => router.push('/login'))
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async postAuthRegister(data: { display_name: string; email: string; username: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      const userStore = useUserStore()
      const navigatorLanguage = navigator.language || undefined
      return await api
        .post('/auth/register', { ...data, locale: navigatorLanguage })
        .then((response) => {
          const authData: AuthResponse = response.data
          // Store both token and user data
          if (authData.access_token) {
            LocalStorage.set('access_token', authData.access_token)
          }
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

    async postAuthLogin(email: string, password: string) {
      this._isLoading = true
      const userStore = useUserStore()
      const navigatorLanguage = navigator.language || undefined
      return await api
        .post('/auth/login', { email, password, locale: navigatorLanguage })
        .then((response) => {
          const authData: AuthResponse = response.data
          // Store both token and user data
          if (authData.access_token) {
            LocalStorage.set('access_token', authData.access_token)
          }
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

      // Clear auth data FIRST to prevent interceptor from triggering
      const token = LocalStorage.getItem('access_token')
      LocalStorage.clear()
      localStorage.removeItem('auth')
      this.$reset()

      // Then try to call logout API (ignore errors since we already cleared local data)
      if (token) {
        try {
          await api.post('/auth/logout')
        } catch (error) {
          // Ignore errors - we already logged out locally
          console.debug('Logout API call failed (expected):', error)
        }
      }

      this._isLoading = false
      router.push('/login')
    },

    async putAuthPassword(data: { current_password?: string; password: string; password_confirmation: string }) {
      this._isLoading = true
      const userStore = useUserStore()
      const wasPasswordSet = userStore.me.has_password_set
      return await api
        .put('/auth/password', data)
        .then((response) => {
          if (response.data.user) {
            userStore.setMe(response.data.user)
            LocalStorage.set('user', response.data.user)
          }
          Notify.create({
            message: wasPasswordSet ? i18n.global.t('password-updated') : i18n.global.t('password-set'),
            type: 'positive'
          })
          return response.data
        })
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
      const navigatorLanguage = navigator.language || undefined
      return await api
        .post('/auth/google', { id_token: idToken, locale: navigatorLanguage })
        .then((response) => {
          const authData: AuthResponse = response.data
          // Store both token and user data
          if (authData.access_token) {
            LocalStorage.set('access_token', authData.access_token)
          }
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

    async putAuthGoogleConnect(idToken: string, action: 'connect' | 'update_email') {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .put('/auth/google', { id_token: idToken, action })
        .then((response) => {
          if (response.data.user) {
            userStore.setMe(response.data.user)
            LocalStorage.set('user', response.data.user)
          }
          Notify.create({ message: response.data.message || i18n.global.t('google-connected'), type: 'positive' })
          return response.data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-connecting-google'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    async deleteAuthGoogle() {
      this._isLoading = true
      const userStore = useUserStore()
      return await api
        .delete('/auth/google')
        .then((response) => {
          userStore.updateMe({ has_google_connected: false })
          Notify.create({ message: i18n.global.t('google-disconnected'), type: 'positive' })
          return response.data
        })
        .catch((error) => {
          Notify.create({ message: error.response?.data?.message || i18n.global.t('error-disconnecting-google'), type: 'negative' })
          throw error
        })
        .finally(() => (this._isLoading = false))
    },

    isAuthenticatedCheck(): boolean {
      const userStore = useUserStore()
      return Boolean(userStore.me.id)
    },

    restoreSession(): boolean {
      const userData = LocalStorage.getItem('user')
      const userStore = useUserStore()

      if (userData && typeof userData === 'object') {
        userStore.setMe(userData as User)
        return true
      }

      return false
    },

    async postAuthVerifyEmail() {
      this._isLoading = true
      return await api
        .post('/auth/verify-email')
        .then((response) => {
          Notify.create({ message: i18n.global.t('verification-email-sent'), type: 'positive' })
          return response.data
        })
        .catch((error) => {
          if (error.response?.status === 400 && error.response?.data?.message === 'Email already verified') {
            Notify.create({ message: i18n.global.t('email-already-verified'), type: 'info' })
            this.getMe()
          } else {
            Notify.create({ message: error.response?.data?.message || i18n.global.t('error-sending-verification'), type: 'negative' })
          }
          throw error
        })
        .finally(() => (this._isLoading = false))
    }
  }
})
