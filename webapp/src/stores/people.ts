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

    // TODO: Implement follow/unfollow functionality when API endpoints are available
    follow() {
      // Placeholder method - API call to follow user will be implemented
      console.warn('Follow functionality not yet implemented')
    },

    unfollow() {
      // Placeholder method - API call to unfollow user will be implemented
      console.warn('Unfollow functionality not yet implemented')
    }
  }
})
