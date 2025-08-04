import type { User } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { useUserStore } from './user'

export const usePeopleStore = defineStore('people', {
  state: () => ({
    _isLoading: false,
    _person: {} as User
  }),

  persist: true,

  getters: {
    isLoading: (state) => state._isLoading,
    people: () => {
      const userStore = useUserStore()
      return userStore.people
    },
    person: (state) => state._person
  },

  actions: {
    async getUserByIdentifier(identifier: string) {
      if (!identifier) {
        this.$patch({ _person: {} })
        return Promise.resolve()
      }
      this._isLoading = true
      return await api
        .get(`/users/${identifier}`)
        .then((response) => this.$patch({ _person: response.data }))
        .catch(() => this.$patch({ _person: {} }))
        .finally(() => (this._isLoading = false))
    },

    // TODO: Follow/Unfollow functionality - Future feature
    follow(userId: string) {
      // Implementation will be added when social features are developed
      throw new Error('Follow functionality is not yet available')
    },

    unfollow(userId: string) {
      // Implementation will be added when social features are developed
      throw new Error('Unfollow functionality is not yet available')
    }
  }
})
