import type { Meta, User } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useUserStore = defineStore('user', {
  state: () => ({
    _isLoading: false,
    _me: {} as User,
    _meta: {} as Meta,
    _user: {} as User,
    _users: [] as User[]
  }),

  getters: {
    isLoading: (state) => state._isLoading,
    me: (state) => state._me,
    meta: (state) => state._meta,
    user: (state) => state._user,
    users: (state) => state._users
  },

  actions: {
    async getUsers(params: object) {
      this._isLoading = true
      return await api
        .get('/users', { params })
        .then((response) => {
          this._meta = response.data.meta
          this.$patch({ _users: response.data.data || response.data })
        })
        .catch((error) => Notify.create({ message: error.response.data.message, type: 'negative' }))
        .finally(() => (this._isLoading = false))
    },

    async getUser(identifier: string) {
      if (!identifier) {
        this.$patch({ _user: {} })
        return Promise.resolve()
      }
      this._isLoading = true
      return await api
        .get(`/users/${identifier}`)
        .then((response) => this.$patch({ _user: response.data }))
        .catch(() => this.$patch({ _user: {} }))
        .finally(() => (this._isLoading = false))
    },

    async getCheckUsername(username: string) {
      return await api.get(`/auth/check-username?username=${username}`).then((response) => response.data.exists)
    },

    // Set the current logged in user
    setMe(user: User) {
      this._me = user
    },

    // Update a specific property of the current user
    updateMe(updates: Partial<User>) {
      this._me = { ...this._me, ...updates }
    }
  }
})
