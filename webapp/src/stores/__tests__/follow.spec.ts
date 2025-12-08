import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { User, FollowRequest } from '@/models'

// Hoist mock before imports
const mockAxios = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  delete: vi.fn(),
}))

vi.mock('@/utils/axios', () => ({
  default: mockAxios,
}))

import { useFollowStore } from '../follow'

// Mock i18n
vi.mock('@/locales', () => ({
  i18n: {
    global: {
      t: (key: string) => key,
    },
  },
}))

describe('Follow Store', () => {
  const mockUser: User = {
    id: 'U-TEST-1234',
    display_name: 'Test User',
    email: 'test@example.com',
    username: 'testuser',
  }

  const mockFollowRequest: FollowRequest = {
    id: 1,
    follower: mockUser,
    created_at: '2024-01-01T00:00:00Z',
  }

  const mockFollowers: User[] = [
    mockUser,
    {
      id: 'U-TEST-5678',
      display_name: 'Another User',
      email: 'another@example.com',
      username: 'anotheruser',
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useFollowStore()

      expect(store._followStatus).toEqual({})
      expect(store._followers).toEqual({})
      expect(store._following).toEqual({})
      expect(store._isLoading).toBe(false)
    })
  })

  describe('Getters', () => {
    it('isFollowing should return follow status', () => {
      const store = useFollowStore()
      expect(store.isFollowing('U-TEST-1234')).toBe(false)

      store._followStatus['U-TEST-1234'] = {
        is_following: true,
        is_followed_by: false,
        mutual_follow: false,
      }
      expect(store.isFollowing('U-TEST-1234')).toBe(true)
    })

    it('isFollowedBy should return follower status', () => {
      const store = useFollowStore()
      expect(store.isFollowedBy('U-TEST-1234')).toBe(false)

      store._followStatus['U-TEST-1234'] = {
        is_following: false,
        is_followed_by: true,
        mutual_follow: false,
      }
      expect(store.isFollowedBy('U-TEST-1234')).toBe(true)
    })

    it('isMutualFollow should return mutual follow status', () => {
      const store = useFollowStore()
      expect(store.isMutualFollow('U-TEST-1234')).toBe(false)

      store._followStatus['U-TEST-1234'] = {
        is_following: true,
        is_followed_by: true,
        mutual_follow: true,
      }
      expect(store.isMutualFollow('U-TEST-1234')).toBe(true)
    })
  })

  describe('postUserFollow', () => {
    it('should follow public user immediately', async () => {
      mockAxios.post.mockResolvedValueOnce({
        data: {
          success: true,
          data: { status: 'accepted' },
        },
      })

      const store = useFollowStore()
      const result = await store.postUserFollow('U-TEST-1234')

      expect(mockAxios.post).toHaveBeenCalledWith('/users/U-TEST-1234/follow')
      expect(result.success).toBe(true)
      expect(store._followStatus['U-TEST-1234'].is_following).toBe(true)
    })

    it('should create pending follow request for private user', async () => {
      mockAxios.post.mockResolvedValueOnce({
        data: {
          success: true,
          data: { status: 'pending' },
        },
      })

      const store = useFollowStore()
      const result = await store.postUserFollow('U-PRIVATE-USER')

      expect(result.success).toBe(true)
      expect(result.data?.status).toBe('pending')
      expect(store._followStatus['U-PRIVATE-USER'].is_following).toBe(false)
    })

    it('should handle error when following fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Cannot follow yourself' } },
      })

      const store = useFollowStore()

      await expect(store.postUserFollow('U-SELF')).rejects.toThrow()
    })
  })

  describe('deleteUserFollow', () => {
    it('should unfollow user', async () => {
      mockAxios.delete.mockResolvedValueOnce({
        data: {
          success: true,
          data: { was_pending: false },
        },
      })

      const store = useFollowStore()
      store._followStatus['U-TEST-1234'] = {
        is_following: true,
        is_followed_by: false,
        mutual_follow: false,
      }

      const result = await store.deleteUserFollow('U-TEST-1234')

      expect(mockAxios.delete).toHaveBeenCalledWith('/users/U-TEST-1234/follow')
      expect(result.success).toBe(true)
      expect(store._followStatus['U-TEST-1234'].is_following).toBe(false)
    })

    it('should cancel pending follow request', async () => {
      mockAxios.delete.mockResolvedValueOnce({
        data: {
          success: true,
          data: { was_pending: true },
        },
      })

      const store = useFollowStore()
      const result = await store.deleteUserFollow('U-TEST-1234')

      expect(result.data?.was_pending).toBe(true)
    })

    it('should handle error when unfollowing fails', async () => {
      mockAxios.delete.mockRejectedValueOnce({
        response: { data: { message: 'Not following this user' } },
      })

      const store = useFollowStore()

      await expect(store.deleteUserFollow('U-NOT-FOLLOWING')).rejects.toThrow()
    })
  })

  describe('getUserFollowers', () => {
    it('should fetch followers list', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: mockFollowers } })

      const store = useFollowStore()
      const result = await store.getUserFollowers('U-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/users/U-TEST-1234/followers')
      expect(result).toEqual(mockFollowers)
      expect(store._followers['U-TEST-1234']).toEqual(mockFollowers)
      expect(store._isLoading).toBe(false)
    })

    it('should return cached followers', async () => {
      const store = useFollowStore()
      store._followers['U-TEST-1234'] = mockFollowers

      const result = await store.getUserFollowers('U-TEST-1234')

      expect(mockAxios.get).not.toHaveBeenCalled()
      expect(result).toEqual(mockFollowers)
    })

    it('should handle error when fetching followers fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'User not found' } },
      })

      const store = useFollowStore()
      const result = await store.getUserFollowers('U-INVALID')

      expect(result).toBeNull()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('getUserFollowing', () => {
    it('should fetch following list', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: mockFollowers } })

      const store = useFollowStore()
      const result = await store.getUserFollowing('U-TEST-1234')

      expect(mockAxios.get).toHaveBeenCalledWith('/users/U-TEST-1234/following')
      expect(result).toEqual(mockFollowers)
      expect(store._following['U-TEST-1234']).toEqual(mockFollowers)
      expect(store._isLoading).toBe(false)
    })

    it('should return cached following', async () => {
      const store = useFollowStore()
      store._following['U-TEST-1234'] = mockFollowers

      const result = await store.getUserFollowing('U-TEST-1234')

      expect(mockAxios.get).not.toHaveBeenCalled()
      expect(result).toEqual(mockFollowers)
    })

    it('should handle error when fetching following fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'User not found' } },
      })

      const store = useFollowStore()
      const result = await store.getUserFollowing('U-INVALID')

      expect(result).toBeNull()
      expect(store._isLoading).toBe(false)
    })
  })

  describe('getFollowRequests', () => {
    it('should fetch pending follow requests', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: { data: [mockFollowRequest] } })

      const store = useFollowStore()
      const result = await store.getFollowRequests()

      expect(mockAxios.get).toHaveBeenCalledWith('/follow-requests')
      expect(result).toEqual([mockFollowRequest])
      expect(store._isLoading).toBe(false)
    })

    it('should return empty array on error', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Server error' } },
      })

      const store = useFollowStore()
      const result = await store.getFollowRequests()

      expect(result).toEqual([])
      expect(store._isLoading).toBe(false)
    })
  })

  describe('acceptFollowRequest', () => {
    it('should accept follow request', async () => {
      mockAxios.post.mockResolvedValueOnce({
        data: { success: true },
      })

      const store = useFollowStore()
      const result = await store.acceptFollowRequest(1)

      expect(mockAxios.post).toHaveBeenCalledWith('/follow-requests/1')
      expect(result).toBe(true)
    })

    it('should return false on failure', async () => {
      mockAxios.post.mockResolvedValueOnce({
        data: { success: false },
      })

      const store = useFollowStore()
      const result = await store.acceptFollowRequest(1)

      expect(result).toBe(false)
    })

    it('should handle error when accepting fails', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Request not found' } },
      })

      const store = useFollowStore()
      const result = await store.acceptFollowRequest(999)

      expect(result).toBe(false)
    })
  })

  describe('rejectFollowRequest', () => {
    it('should reject follow request', async () => {
      mockAxios.delete.mockResolvedValueOnce({
        data: { success: true },
      })

      const store = useFollowStore()
      const result = await store.rejectFollowRequest(1)

      expect(mockAxios.delete).toHaveBeenCalledWith('/follow-requests/1')
      expect(result).toBe(true)
    })

    it('should return false on failure', async () => {
      mockAxios.delete.mockResolvedValueOnce({
        data: { success: false },
      })

      const store = useFollowStore()
      const result = await store.rejectFollowRequest(1)

      expect(result).toBe(false)
    })

    it('should handle error when rejecting fails', async () => {
      mockAxios.delete.mockRejectedValueOnce({
        response: { data: { message: 'Request not found' } },
      })

      const store = useFollowStore()
      const result = await store.rejectFollowRequest(999)

      expect(result).toBe(false)
    })
  })

  describe('clearFollowStatus', () => {
    it('should clear specific user follow status', () => {
      const store = useFollowStore()
      store._followStatus = {
        'U-TEST-1234': { is_following: true, is_followed_by: false, mutual_follow: false },
        'U-TEST-5678': { is_following: false, is_followed_by: true, mutual_follow: false },
      }

      store.clearFollowStatus('U-TEST-1234')

      expect(store._followStatus['U-TEST-1234']).toBeUndefined()
      expect(store._followStatus['U-TEST-5678']).toBeDefined()
    })

    it('should clear all follow statuses', () => {
      const store = useFollowStore()
      store._followStatus = {
        'U-TEST-1234': { is_following: true, is_followed_by: false, mutual_follow: false },
        'U-TEST-5678': { is_following: false, is_followed_by: true, mutual_follow: false },
      }

      store.clearFollowStatus()

      expect(store._followStatus).toEqual({})
    })
  })

  describe('clearFollowersData', () => {
    it('should clear specific user followers/following data', () => {
      const store = useFollowStore()
      store._followers = { 'U-TEST-1234': mockFollowers, 'U-TEST-5678': mockFollowers }
      store._following = { 'U-TEST-1234': mockFollowers, 'U-TEST-5678': mockFollowers }

      store.clearFollowersData('U-TEST-1234')

      expect(store._followers['U-TEST-1234']).toBeUndefined()
      expect(store._following['U-TEST-1234']).toBeUndefined()
      expect(store._followers['U-TEST-5678']).toBeDefined()
      expect(store._following['U-TEST-5678']).toBeDefined()
    })

    it('should clear all followers/following data', () => {
      const store = useFollowStore()
      store._followers = { 'U-TEST-1234': mockFollowers }
      store._following = { 'U-TEST-1234': mockFollowers }

      store.clearFollowersData()

      expect(store._followers).toEqual({})
      expect(store._following).toEqual({})
    })
  })

  describe('removeFromFollowingList', () => {
    it('should remove user from following list', () => {
      const store = useFollowStore()
      store._following['U-PROFILE'] = [...mockFollowers]

      store.removeFromFollowingList('U-PROFILE', 'U-TEST-1234')

      expect(store._following['U-PROFILE'].find((u) => u.id === 'U-TEST-1234')).toBeUndefined()
      expect(store._following['U-PROFILE']).toHaveLength(1)
    })

    it('should do nothing if profile not in cache', () => {
      const store = useFollowStore()

      store.removeFromFollowingList('U-NOT-CACHED', 'U-TEST-1234')

      expect(store._following['U-NOT-CACHED']).toBeUndefined()
    })
  })
})
