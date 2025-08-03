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

    follow() {
      // TODO: Implement follow functionality in API
      throw new Error('Follow functionality not yet implemented')
    },

    unfollow() {
      // TODO: Implement unfollow functionality in API
      throw new Error('Unfollow functionality not yet implemented')
    }
  }
})
