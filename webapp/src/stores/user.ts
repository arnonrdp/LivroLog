import type { Meta, User } from '@/models'
import router from '@/router'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'
import { useAuthStore } from './auth'

export const useUserStore = defineStore('user', {
  state: () => ({
    _isLoading: false,
    _meta: {} as Meta,
    _people: [] as User[]
  }),

  getters: {
    isLoading: (state) => state._isLoading,
    isFollowing: (state) => (personId: number) => {
      // Para evitar dependência circular, vamos implementar de forma simples
      // A store auth pode definir o following do usuário
      return false // Implementar quando necessário
    },
    meta: (state) => state._meta,
    people: (state) => state._people
  },

  actions: {
    async getUsers(params: object) {
      this._isLoading = true
      return await api
        .get('/users', { params })
        .then((response) => {
          this._meta = response.data.meta
          this.$patch({ _people: response.data.data || response.data })
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async putAccount(data: object) {
      const authStore = useAuthStore()
      authStore._isLoading = true
      return await api
        .put('/account', data)
        .then((response) => {
          authStore.setUser(response.data.user)
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (authStore._isLoading = false))
    },

    async deleteAccount() {
      const authStore = useAuthStore()
      authStore._isLoading = true
      return await api
        .delete('/account')
        .then(() => router.push('/login'))
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (authStore._isLoading = false))
    },

    async getCheckUsername(username: string) {
      return await api.get(`/check-username?username=${username}`).then((response) => response.data.exists)
    },

    async putProfile(data: { display_name?: string; username?: string; email?: string; shelf_name?: string; locale?: string }) {
      const authStore = useAuthStore()
      authStore._isLoading = true
      return await api
        .put('/profile', data)
        .then((response) => {
          authStore.setUser(response.data.user)
          return response.data
        })
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
        .finally(() => (authStore._isLoading = false))
    }
  }
})
