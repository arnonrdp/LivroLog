import { i18n } from '@/locales'
import type { Book, CreateTagRequest, Tag, TagListResponse, TagMeta, TagResponse, UpdateTagRequest } from '@/models'
import { TAG_COLORS } from '@/models'
import api from '@/utils/axios'
import { defineStore } from 'pinia'
import { Notify } from 'quasar'
import { useUserStore } from './user'

const defaultMeta: TagMeta = {
  colors: [...TAG_COLORS],
  suggestions: [] // Suggestions are loaded from the API in the correct language
}

export const useTagStore = defineStore('tag', {
  state: () => ({
    _tags: [] as Tag[],
    _meta: { ...defaultMeta } as TagMeta,
    _isLoading: false,
    _bookTags: {} as Record<string, Tag[]> // bookId -> tags
  }),

  getters: {
    tags: (state) => state._tags,
    meta: (state) => state._meta,
    isLoading: (state) => state._isLoading,

    // Get tags for a specific book
    getTagsForBook: (state) => (bookId: string) => state._bookTags[bookId] || []
  },

  actions: {
    getTags(): Promise<TagListResponse | null> {
      this._isLoading = true

      return api
        .get('/tags')
        .then((response) => {
          const data: TagListResponse = response.data
          this._tags = data.data
          this._meta = data.meta
          return data
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('tags.error-loading'),
            type: 'negative'
          })
          return null
        })
        .finally(() => {
          this._isLoading = false
        })
    },

    postTag(data: CreateTagRequest): Promise<Tag | null> {
      return api
        .post('/tags', data)
        .then((response) => {
          const tagResponse: TagResponse = response.data
          this._tags.push(tagResponse.data)
          // Sort by name
          this._tags.sort((a, b) => a.name.localeCompare(b.name))
          return tagResponse.data
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('tags.error-creating'),
            type: 'negative'
          })
          return null
        })
    },

    putTag(tagId: string, data: UpdateTagRequest): Promise<Tag | null> {
      return api
        .put(`/tags/${tagId}`, data)
        .then((response) => {
          const tagResponse: TagResponse = response.data
          const index = this._tags.findIndex((t) => t.id === tagId)
          if (index !== -1) {
            this._tags[index] = tagResponse.data
          }
          // Resort if name changed
          if (data.name) {
            this._tags.sort((a, b) => a.name.localeCompare(b.name))
          }
          return tagResponse.data
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('tags.error-updating'),
            type: 'negative'
          })
          return null
        })
    },

    deleteTag(tagId: string): Promise<boolean> {
      return api
        .delete(`/tags/${tagId}`)
        .then(() => {
          this._tags = this._tags.filter((t) => t.id !== tagId)
          // Remove from all book tags in _bookTags
          Object.keys(this._bookTags).forEach((bookId) => {
            const bookTags = this._bookTags[bookId]
            if (bookTags) {
              this._bookTags[bookId] = bookTags.filter((t) => t.id !== tagId)
            }
          })

          // Also remove from all books in userStore.me.books
          const userStore = useUserStore()
          const books = userStore.me.books || []
          const updatedBooks = books.map((book: Book & { tags?: Tag[] }) => {
            if (book.tags && book.tags.length > 0) {
              return { ...book, tags: book.tags.filter((t) => t.id !== tagId) }
            }
            return book
          })
          userStore.updateMe({ books: updatedBooks })

          return true
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('tags.error-deleting'),
            type: 'negative'
          })
          return false
        })
    },

    getBookTags(bookId: string): Promise<Tag[] | null> {
      return api
        .get(`/user/books/${bookId}/tags`)
        .then((response) => {
          const tags: Tag[] = response.data.data
          // Use spread to ensure proper reactivity
          this._bookTags = { ...this._bookTags, [bookId]: tags }
          return tags
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('tags.error-loading'),
            type: 'negative'
          })
          return null
        })
    },

    postBookTagAdd(bookId: string, tagId: string): Promise<boolean> {
      return api
        .post(`/user/books/${bookId}/tags/${tagId}`)
        .then(() => {
          // Add tag to local book tags
          const tag = this._tags.find((t) => t.id === tagId)
          if (tag) {
            // Ensure proper reactivity by creating a new object reference
            const currentTags = this._bookTags[bookId] || []
            if (!currentTags.find((t) => t.id === tagId)) {
              const newTags = [...currentTags, tag].sort((a, b) => a.name.localeCompare(b.name))
              // Use spread to create a new object reference for reactivity
              this._bookTags = { ...this._bookTags, [bookId]: newTags }

              // Also update the book.tags in userStore for consistency
              this._syncBookTagsToUserStore(bookId, newTags)
            }
          } else {
            // Tag not in local store - fetch it from API to ensure consistency
            console.warn(`Tag ${tagId} not found in local store, refreshing tags...`)
            this.getTags()
            this.getBookTags(bookId)
          }
          return true
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('tags.error-adding'),
            type: 'negative'
          })
          return false
        })
    },

    deleteBookTag(bookId: string, tagId: string): Promise<boolean> {
      return api
        .delete(`/user/books/${bookId}/tags/${tagId}`)
        .then(() => {
          // Remove tag from local book tags with proper reactivity
          const currentTags = this._bookTags[bookId] || []
          const newTags = currentTags.filter((t) => t.id !== tagId)
          this._bookTags = { ...this._bookTags, [bookId]: newTags }

          // Also update the book.tags in userStore for consistency
          this._syncBookTagsToUserStore(bookId, newTags)

          return true
        })
        .catch((error) => {
          Notify.create({
            message: error.response?.data?.message || i18n.global.t('tags.error-removing'),
            type: 'negative'
          })
          return false
        })
    },

    // Sync tags to the book object in userStore.me.books
    _syncBookTagsToUserStore(bookId: string, tags: Tag[]): void {
      const userStore = useUserStore()
      const books = userStore.me.books || []
      const bookIndex = books.findIndex((b) => b.id === bookId)
      if (bookIndex !== -1) {
        const updatedBooks = books.map((book, index) => (index === bookIndex ? { ...book, tags } : book))
        userStore.updateMe({ books: updatedBooks })
      }
    },

    clearTags(): void {
      this._tags = []
      this._meta = { ...defaultMeta }
      this._bookTags = {}
    }
  }
})
