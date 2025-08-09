import { i18n } from '@/locales'
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
    // Helper method to handle loading states and error notifications
    async _withLoading<T>(work: () => Promise<T>, loadingKey: '_isLoading' | '_isGoogleLoading' = '_isLoading', notifyOnError = true): Promise<T> {
      try {
        this[loadingKey] = true
        return await work()
      } catch (error: any) {
        if (notifyOnError) {
          Notify.create({ message: error.response?.data?.message || error.message, type: 'negative' })
        }
        throw error
      } finally {
        this[loadingKey] = false
      }
    },

    setUser(user: User) {
      this.$patch({ _user: user })
    },

    async getAuthMe() {
      return this._withLoading(async () => {
        try {
          const response = await api.get('/auth/me')
          this.$patch({ _user: response.data })

          if (this.isAuthenticated) {
            router.push('/')
          }
          return response.data
        } catch (error) {
          // Clear user data if auth fails
          this.$patch({ _user: {} })
          throw error
        }
      })
    },

    async postAuthLogin(email: string, password: string) {
      return this._withLoading(async () => {
        const response = await api.post('/auth/login', { email, password })
        const authData: AuthResponse = response.data

        localStorage.setItem('auth_token', authData.access_token)
        LocalStorage.set('user', authData.user)
        this.$patch({ _user: authData.user })
        router.push('/')

        return authData
      })
    },

    async postAuthRegister(data: { display_name: string; email: string; username: string; password: string; password_confirmation: string }) {
      return this._withLoading(async () => {
        const response = await api.post('/auth/register', data)
        const authData: AuthResponse = response.data

        localStorage.setItem('auth_token', authData.access_token)
        LocalStorage.set('user', authData.user)
        this.$patch({ _user: authData.user })
        router.push('/')

        return authData
      })
    },

    async postAuthLogout() {
      return this._withLoading(
        async () => {
          try {
            await api.post('/auth/logout')
          } catch (error) {
            // Continue with logout even if API call fails
            console.error('Logout API error:', error)
          }

          // Clear all auth data
          this.$reset()
          localStorage.removeItem('auth_token')
          LocalStorage.clear()

          // Clear persisted store data
          localStorage.removeItem('auth')

          router.push('/login')
        },
        '_isLoading',
        false // Don't notify on logout errors
      )
    },

    async putPassword(data: { current_password: string; password: string; password_confirmation: string }) {
      return this._withLoading(async () => {
        const response = await api.put('/password', data)
        return response.data
      })
    },

    async postForgotPassword(email: string) {
      return this._withLoading(
        async () => {
          const response = await api.post('/auth/forgot-password', { email })
          return response.data
        },
        '_isLoading',
        false // Handle errors manually for forgot password
      )
    },

    async postResetPassword(data: { token: string; email: string; password: string; password_confirmation: string }) {
      return this._withLoading(async () => {
        await api.post('/auth/reset-password', data).then((response) => {
          Notify.create({ message: i18n.global.t('password-reset-success') })
          return response.data
        })
      })
    },

    async postGoogleSignIn(idToken: string) {
      return this._withLoading(async () => {
        const response = await api.post('/auth/google', { id_token: idToken })
        const authData: AuthResponse = response.data

        localStorage.setItem('auth_token', authData.access_token)
        LocalStorage.set('user', authData.user)
        this.$patch({ _user: authData.user })
        Notify.create({ message: 'Login with Google successful!', type: 'positive' })
        router.push('/')

        return authData
      }, '_isGoogleLoading')
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
    },

    async refreshUser() {
      return this._withLoading(
        async () => {
          const response = await api.get('/auth/me')
          this.$patch({ _user: response.data })
          LocalStorage.set('user', response.data)
          return response.data
        },
        '_isLoading',
        false
      )
    }
  }
})
