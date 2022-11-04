import { db } from '@/firebase'
import type { Book } from '@/models'
import { collection, getDocs } from 'firebase/firestore'
import { defineStore } from 'pinia'
import { LocalStorage } from 'quasar'

export const useShowcaseStore = defineStore('showcase', {
  state: () => ({
    _showcase: [] as Book[]
  }),

  getters: {
    getShowcase: (state) => state._showcase
  },

  actions: {
    async fetchShowcase() {
      await getDocs(collection(db, 'showcase'))
        .then((querySnapshot) => {
          const showcase = querySnapshot.docs.map((docBook) => ({ id: docBook.id, ...docBook.data() }))
          this._showcase = []
          this.$patch({ _showcase: showcase })
          return showcase
        })
        .catch((error) => console.error(error))
      if (this.getShowcase) {
        LocalStorage.set('showcase', this._showcase)
      }
    }
  }
})
