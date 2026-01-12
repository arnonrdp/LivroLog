import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Tag, TagListResponse, TagResponse } from '@/models'
import { TAG_COLORS } from '@/models'

// Hoist mock before imports
const mockAxios = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn()
}))

vi.mock('@/utils/axios', () => ({
  default: mockAxios
}))

import { useTagStore } from '../tag'

// Mock i18n
vi.mock('@/locales', () => ({
  i18n: {
    global: {
      t: (key: string) => key
    }
  }
}))

// Mock Quasar Notify
vi.mock('quasar', () => ({
  Notify: {
    create: vi.fn()
  }
}))

describe('Tag Store', () => {
  const mockTag: Tag = {
    id: 'T-TEST-1234',
    name: 'Favoritos',
    color: '#EF4444',
    books_count: 5,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z'
  }

  const mockTags: Tag[] = [
    mockTag,
    {
      id: 'T-TEST-5678',
      name: 'Doação',
      color: '#22C55E',
      books_count: 3,
      created_at: '2024-01-01T00:00:00Z',
      updated_at: '2024-01-01T00:00:00Z'
    }
  ]

  const mockSuggestions = ['Doação', 'Favoritos', 'Emprestado']

  const mockTagListResponse: TagListResponse = {
    data: mockTags,
    meta: {
      colors: [...TAG_COLORS],
      suggestions: mockSuggestions
    }
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useTagStore()

      expect(store._tags).toEqual([])
      expect(store._isLoading).toBe(false)
      expect(store._bookTags).toEqual({})
      expect(store._meta.colors).toEqual([...TAG_COLORS])
      expect(store._meta.suggestions).toEqual([]) // Suggestions are loaded from API
    })
  })

  describe('Getters', () => {
    it('tags getter should return state value', () => {
      const store = useTagStore()

      store._tags = mockTags
      expect(store.tags).toEqual(mockTags)
    })

    it('isLoading getter should return state value', () => {
      const store = useTagStore()
      expect(store.isLoading).toBe(false)

      store._isLoading = true
      expect(store.isLoading).toBe(true)
    })

    it('meta getter should return state value', () => {
      const store = useTagStore()

      expect(store.meta.colors).toEqual([...TAG_COLORS])
      expect(store.meta.suggestions).toEqual([]) // Suggestions are loaded from API
    })

    it('getTagsForBook should return tags for a specific book', () => {
      const store = useTagStore()

      store._bookTags = { 'B-TEST-1234': mockTags }
      expect(store.getTagsForBook('B-TEST-1234')).toEqual(mockTags)
      expect(store.getTagsForBook('B-UNKNOWN')).toEqual([])
    })
  })

  describe('getTags', () => {
    it('should fetch tags and update state', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockTagListResponse })

      const store = useTagStore()
      const result = await store.getTags()

      expect(mockAxios.get).toHaveBeenCalledWith('/tags')
      expect(result).toEqual(mockTagListResponse)
      expect(store._tags).toEqual(mockTags)
      expect(store._meta).toEqual(mockTagListResponse.meta)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when fetching tags fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Unauthorized' } }
      })

      const store = useTagStore()
      const result = await store.getTags()

      expect(result).toBeNull()
      expect(store._tags).toEqual([])
      expect(store._isLoading).toBe(false)
    })

    it('should set loading state during fetch', async () => {
      let loadingDuringFetch = false
      mockAxios.get.mockImplementation(() => {
        const store = useTagStore()
        loadingDuringFetch = store._isLoading
        return Promise.resolve({ data: mockTagListResponse })
      })

      const store = useTagStore()
      await store.getTags()

      expect(loadingDuringFetch).toBe(true)
    })
  })

  describe('postTag', () => {
    it('should create a new tag', async () => {
      const newTag: Tag = { ...mockTag, id: 'T-NEW-1234', name: 'New Tag' }
      const response: TagResponse = { data: newTag }
      mockAxios.post.mockResolvedValueOnce({ data: response })

      const store = useTagStore()
      const result = await store.postTag({ name: 'New Tag', color: '#EF4444' })

      expect(mockAxios.post).toHaveBeenCalledWith('/tags', { name: 'New Tag', color: '#EF4444' })
      expect(result).toEqual(newTag)
      expect(store._tags).toContainEqual(newTag)
    })

    it('should sort tags alphabetically after adding', async () => {
      const store = useTagStore()
      store._tags = [
        { ...mockTag, id: 'T-1', name: 'Zebra' },
        { ...mockTag, id: 'T-2', name: 'Apple' }
      ]

      const newTag: Tag = { ...mockTag, id: 'T-3', name: 'Middle' }
      mockAxios.post.mockResolvedValueOnce({ data: { data: newTag } })

      await store.postTag({ name: 'Middle', color: '#EF4444' })

      expect(store._tags.map((t) => t.name)).toEqual(['Apple', 'Middle', 'Zebra'])
    })

    it('should handle error when creating tag fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Tag already exists' } }
      })

      const store = useTagStore()
      const result = await store.postTag({ name: 'Test', color: '#EF4444' })

      expect(result).toBeNull()
    })
  })

  describe('putTag', () => {
    it('should update an existing tag', async () => {
      const updatedTag: Tag = { ...mockTag, name: 'Updated Name' }
      mockAxios.put.mockResolvedValueOnce({ data: { data: updatedTag } })

      const store = useTagStore()
      store._tags = [mockTag]

      const result = await store.putTag('T-TEST-1234', { name: 'Updated Name' })

      expect(mockAxios.put).toHaveBeenCalledWith('/tags/T-TEST-1234', { name: 'Updated Name' })
      expect(result).toEqual(updatedTag)
      expect(store._tags[0].name).toBe('Updated Name')
    })

    it('should resort tags if name is changed', async () => {
      const store = useTagStore()
      store._tags = [
        { ...mockTag, id: 'T-1', name: 'Alpha' },
        { ...mockTag, id: 'T-2', name: 'Beta' }
      ]

      const updatedTag: Tag = { ...mockTag, id: 'T-1', name: 'Zeta' }
      mockAxios.put.mockResolvedValueOnce({ data: { data: updatedTag } })

      await store.putTag('T-1', { name: 'Zeta' })

      expect(store._tags.map((t) => t.name)).toEqual(['Beta', 'Zeta'])
    })

    it('should handle error when updating tag fails', async () => {
      mockAxios.put.mockRejectedValueOnce({
        response: { data: { message: 'Tag not found' } }
      })

      const store = useTagStore()
      const result = await store.putTag('T-INVALID', { name: 'Test' })

      expect(result).toBeNull()
    })
  })

  describe('deleteTag', () => {
    it('should delete a tag', async () => {
      mockAxios.delete.mockResolvedValueOnce({ data: { success: true } })

      const store = useTagStore()
      store._tags = mockTags

      const result = await store.deleteTag('T-TEST-1234')

      expect(mockAxios.delete).toHaveBeenCalledWith('/tags/T-TEST-1234')
      expect(result).toBe(true)
      expect(store._tags.find((t) => t.id === 'T-TEST-1234')).toBeUndefined()
    })

    it('should remove tag from all book tags when deleted', async () => {
      mockAxios.delete.mockResolvedValueOnce({ data: { success: true } })

      const store = useTagStore()
      store._tags = [mockTag]
      store._bookTags = {
        'B-1': [mockTag],
        'B-2': [mockTag, mockTags[1]]
      }

      await store.deleteTag('T-TEST-1234')

      expect(store._bookTags['B-1']).toEqual([])
      expect(store._bookTags['B-2'].find((t) => t.id === 'T-TEST-1234')).toBeUndefined()
    })

    it('should handle error when deleting tag fails', async () => {
      mockAxios.delete.mockRejectedValueOnce({
        response: { data: { message: 'Tag not found' } }
      })

      const store = useTagStore()
      const result = await store.deleteTag('T-INVALID')

      expect(result).toBe(false)
    })
  })

  describe('getBookTags', () => {
    it('should fetch tags for a specific book', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: mockTags } })

      const store = useTagStore()
      const result = await store.getBookTags('B-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/user/books/B-TEST-1234/tags')
      expect(result).toEqual(mockTags)
      expect(store._bookTags['B-TEST-1234']).toEqual(mockTags)
    })

    it('should handle error when fetching book tags fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Book not found' } }
      })

      const store = useTagStore()
      const result = await store.getBookTags('B-INVALID')

      expect(result).toBeNull()
    })
  })

  describe('postBookTagAdd', () => {
    it('should add a tag to a book', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: { success: true } })

      const store = useTagStore()
      store._tags = [mockTag]
      store._bookTags = { 'B-TEST-1234': [] }

      const result = await store.postBookTagAdd('B-TEST-1234', 'T-TEST-1234')

      expect(mockAxios.post).toHaveBeenCalledWith('/user/books/B-TEST-1234/tags/T-TEST-1234')
      expect(result).toBe(true)
      expect(store._bookTags['B-TEST-1234']).toContainEqual(mockTag)
    })

    it('should not add duplicate tag to book tags', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: { success: true } })

      const store = useTagStore()
      store._tags = [mockTag]
      store._bookTags = { 'B-TEST-1234': [mockTag] }

      await store.postBookTagAdd('B-TEST-1234', 'T-TEST-1234')

      expect(store._bookTags['B-TEST-1234'].filter((t) => t.id === 'T-TEST-1234')).toHaveLength(1)
    })

    it('should create book tags array if not exists', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: { success: true } })

      const store = useTagStore()
      store._tags = [mockTag]

      await store.postBookTagAdd('B-NEW-BOOK', 'T-TEST-1234')

      expect(store._bookTags['B-NEW-BOOK']).toContainEqual(mockTag)
    })

    it('should sort tags alphabetically after adding', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: { success: true } })

      const store = useTagStore()
      store._tags = [
        { ...mockTag, id: 'T-1', name: 'Zebra' },
        { ...mockTag, id: 'T-2', name: 'Alpha' }
      ]
      store._bookTags = { 'B-TEST': [{ ...mockTag, id: 'T-1', name: 'Zebra' }] }

      await store.postBookTagAdd('B-TEST', 'T-2')

      expect(store._bookTags['B-TEST'].map((t) => t.name)).toEqual(['Alpha', 'Zebra'])
    })

    it('should handle error when adding tag fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Tag not found' } }
      })

      const store = useTagStore()
      const result = await store.postBookTagAdd('B-TEST-1234', 'T-INVALID')

      expect(result).toBe(false)
    })
  })

  describe('deleteBookTag', () => {
    it('should remove a tag from a book', async () => {
      mockAxios.delete.mockResolvedValueOnce({ data: { success: true } })

      const store = useTagStore()
      store._bookTags = { 'B-TEST-1234': mockTags }

      const result = await store.deleteBookTag('B-TEST-1234', 'T-TEST-1234')

      expect(mockAxios.delete).toHaveBeenCalledWith('/user/books/B-TEST-1234/tags/T-TEST-1234')
      expect(result).toBe(true)
      expect(store._bookTags['B-TEST-1234'].find((t) => t.id === 'T-TEST-1234')).toBeUndefined()
    })

    it('should handle error when removing tag fails', async () => {
      mockAxios.delete.mockRejectedValueOnce({
        response: { data: { message: 'Tag not associated' } }
      })

      const store = useTagStore()
      const result = await store.deleteBookTag('B-TEST-1234', 'T-INVALID')

      expect(result).toBe(false)
    })
  })

  describe('clearTags', () => {
    it('should clear all tag data', () => {
      const store = useTagStore()
      store._tags = mockTags
      store._bookTags = { 'B-TEST-1234': mockTags }
      store._meta = { colors: [], suggestions: ['Test'] }

      store.clearTags()

      expect(store._tags).toEqual([])
      expect(store._bookTags).toEqual({})
      expect(store._meta.colors).toEqual([...TAG_COLORS])
      expect(store._meta.suggestions).toEqual([]) // Resets to empty (API provides suggestions)
    })
  })
})
