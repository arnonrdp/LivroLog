import { defineStore } from 'pinia'
import { resolveShelfTexture, shelfTextureStyle, type ShelfTextureId } from '@/config/shelfTextures'

export const useAppearanceStore = defineStore('appearance', {
  state: () => ({ shelfTexture: 'wood' as ShelfTextureId }),
  persist: true,
  getters: {
    selectedTexture: (state) => resolveShelfTexture(state.shelfTexture),
    shelfStyle: (state) => shelfTextureStyle(state.shelfTexture)
  },
  actions: {
    selectTexture(texture: ShelfTextureId) {
      this.shelfTexture = resolveShelfTexture(texture)
    }
  }
})
