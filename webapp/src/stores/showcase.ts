import type { Book } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'

export const useShowcaseStore = defineStore('showcase', {
  state: () => ({
    _showcase: [] as Book[]
  }),

  persist: true,

  getters: {
    showcase: (state) => state._showcase
  },

  actions: {
    async getShowcase() {
      return await api
        .get('/showcase')
        .then((response) => this.$patch({ _showcase: response.data.data || response.data }))
        .catch((error) => Notify.create({ message: error.response.data.message, color: 'negative' }))
    }
  }
})
