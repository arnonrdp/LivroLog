import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { Review } from '@/models'

// Hoist mock before imports
const mockAxios = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  delete: vi.fn()
}))

vi.mock('@/utils/axios', () => ({
  default: mockAxios
}))

import { useReviewStore } from '../review'

// Mock i18n
vi.mock('@/locales', () => ({
  i18n: {
    global: {
      t: (key: string) => key
    }
  }
}))

describe('Review Store', () => {
  const mockReview: Review = {
    id: 1,
    user_id: 'U-TEST-1234',
    book_id: 'B-TEST-1234',
    title: 'Great Book',
    content: 'This is a fantastic read!',
    rating: 5,
    visibility_level: 'public',
    created_at: '2024-01-01T00:00:00Z'
  }

  const mockReviews: Review[] = [
    mockReview,
    {
      id: 2,
      user_id: 'U-TEST-5678',
      book_id: 'B-TEST-1234',
      title: 'Good Book',
      content: 'Enjoyed reading it',
      rating: 4,
      visibility_level: 'public',
      created_at: '2024-01-02T00:00:00Z'
    }
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useReviewStore()

      expect(store._isLoading).toBe(false)
      expect(store._reviews).toEqual([])
    })
  })

  describe('Getters', () => {
    it('reviews getter should return state value', () => {
      const store = useReviewStore()
      expect(store.reviews).toEqual([])

      store._reviews = mockReviews
      expect(store.reviews).toEqual(mockReviews)
    })
  })

  describe('getReviews', () => {
    it('should fetch single review by id', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockReview })

      const store = useReviewStore()
      const result = await store.getReviews(1)

      expect(mockAxios.get).toHaveBeenCalledWith('/reviews/1')
      expect(result).toEqual(mockReview)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when fetching review fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Review not found' } }
      })

      const store = useReviewStore()
      await store.getReviews(999)

      expect(store._isLoading).toBe(false)
    })
  })

  describe('getReviewsByBook', () => {
    it('should fetch reviews for a book', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: mockReviews } })

      const store = useReviewStore()
      const result = await store.getReviewsByBook('B-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/reviews?book_id=B-TEST-1234')
      expect(result).toEqual(mockReviews)
      expect(store._reviews).toEqual(mockReviews)
      expect(store._isLoading).toBe(false)
    })

    it('should handle empty reviews', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: [] } })

      const store = useReviewStore()
      const result = await store.getReviewsByBook('B-NO-REVIEWS')

      expect(result).toEqual([])
      expect(store._reviews).toEqual([])
    })

    it('should handle error when fetching reviews fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Server error' } }
      })

      const store = useReviewStore()
      await store.getReviewsByBook('B-TEST-1234')

      expect(store._reviews).toEqual([])
      expect(store._isLoading).toBe(false)
    })
  })

  describe('postReviews', () => {
    it('should create a new review', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: mockReview })

      const store = useReviewStore()
      const reviewData = {
        book_id: 'B-TEST-1234',
        title: 'Great Book',
        content: 'This is a fantastic read!',
        rating: 5,
        visibility_level: 'public' as const
      }
      const result = await store.postReviews(reviewData)

      expect(mockAxios.post).toHaveBeenCalledWith('/reviews', reviewData)
      expect(result).toEqual(mockReview)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when creating review fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Validation error' } }
      })

      const store = useReviewStore()
      await store.postReviews({
        book_id: 'B-TEST-1234',
        rating: 5,
        visibility_level: 'public'
      })

      expect(store._isLoading).toBe(false)
    })
  })

  describe('putReviews', () => {
    it('should update an existing review', async () => {
      const updatedReview = { ...mockReview, title: 'Updated Title', rating: 4 }
      mockAxios.put.mockResolvedValueOnce({ data: updatedReview })

      const store = useReviewStore()
      const result = await store.putReviews(1, { title: 'Updated Title', rating: 4 })

      expect(mockAxios.put).toHaveBeenCalledWith('/reviews/1', { title: 'Updated Title', rating: 4 })
      expect(result).toEqual(updatedReview)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when updating review fails', async () => {
      mockAxios.put.mockRejectedValueOnce({
        response: { data: { message: 'Update failed' } }
      })

      const store = useReviewStore()
      await store.putReviews(1, { rating: 6 }) // Invalid rating

      expect(store._isLoading).toBe(false)
    })
  })

  describe('deleteReviews', () => {
    it('should delete a review', async () => {
      mockAxios.delete.mockResolvedValueOnce({ data: {} })

      const store = useReviewStore()
      store._reviews = [...mockReviews]

      const result = await store.deleteReviews(1)

      expect(mockAxios.delete).toHaveBeenCalledWith('/reviews/1')
      expect(result).toBe(true)
      expect(store._reviews.find((r) => r.id === 1)).toBeUndefined()
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when deleting review fails', async () => {
      mockAxios.delete.mockRejectedValueOnce({
        response: { data: { message: 'Delete failed' } }
      })

      const store = useReviewStore()
      store._reviews = [...mockReviews]

      await store.deleteReviews(1)

      expect(store._reviews).toHaveLength(2) // Review should still be there
      expect(store._isLoading).toBe(false)
    })
  })

  describe('toggleReviewVisibility', () => {
    it('should cycle public -> friends', async () => {
      const updatedReview = { ...mockReview, visibility_level: 'friends' as const }
      mockAxios.put.mockResolvedValueOnce({ data: updatedReview })

      const store = useReviewStore()
      store._reviews = [{ ...mockReview, visibility_level: 'public' }]

      const result = await store.toggleReviewVisibility(mockReview)

      expect(mockAxios.put).toHaveBeenCalledWith('/reviews/1', { visibility_level: 'friends' })
      expect(result).toBe('friends')
    })

    it('should cycle friends -> private', async () => {
      const friendsReview = { ...mockReview, visibility_level: 'friends' as const }
      mockAxios.put.mockResolvedValueOnce({ data: { ...friendsReview, visibility_level: 'private' } })

      const store = useReviewStore()
      store._reviews = [friendsReview]

      const result = await store.toggleReviewVisibility(friendsReview)

      expect(mockAxios.put).toHaveBeenCalledWith('/reviews/1', { visibility_level: 'private' })
      expect(result).toBe('private')
    })

    it('should cycle private -> public', async () => {
      const privateReview = { ...mockReview, visibility_level: 'private' as const }
      mockAxios.put.mockResolvedValueOnce({ data: { ...privateReview, visibility_level: 'public' } })

      const store = useReviewStore()
      store._reviews = [privateReview]

      const result = await store.toggleReviewVisibility(privateReview)

      expect(mockAxios.put).toHaveBeenCalledWith('/reviews/1', { visibility_level: 'public' })
      expect(result).toBe('public')
    })
  })

  describe('postReviewsHelpful', () => {
    it('should mark review as helpful', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: {} })

      const store = useReviewStore()
      const result = await store.postReviewsHelpful(1)

      expect(mockAxios.post).toHaveBeenCalledWith('/reviews/1/helpful')
      expect(result).toBe(true)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when marking helpful fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Already marked' } }
      })

      const store = useReviewStore()
      await store.postReviewsHelpful(1)

      expect(store._isLoading).toBe(false)
    })
  })

  describe('clearReviews', () => {
    it('should clear all reviews', () => {
      const store = useReviewStore()
      store._reviews = [...mockReviews]

      store.clearReviews()

      expect(store._reviews).toEqual([])
    })
  })
})
