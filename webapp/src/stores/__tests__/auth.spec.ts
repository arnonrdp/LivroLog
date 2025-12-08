import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { User, AuthResponse } from '@/models'

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

import { useAuthStore } from '../auth'
import { useUserStore } from '../user'

// Mock router
vi.mock('@/router', () => ({
  default: {
    push: vi.fn()
  }
}))

// Mock i18n
vi.mock('@/locales', () => ({
  i18n: {
    global: {
      t: (key: string) => key,
      locale: { value: 'pt-BR' }
    }
  }
}))

describe('Auth Store', () => {
  const mockUser: User = {
    id: 'U-TEST-1234',
    display_name: 'Test User',
    email: 'test@example.com',
    username: 'testuser',
    has_password_set: true
  }

  const mockAuthResponse: AuthResponse = {
    access_token: 'test-token-123',
    token_type: 'Bearer',
    user: mockUser
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    // Reset all mock implementations
    mockAxios.get.mockReset()
    mockAxios.post.mockReset()
    mockAxios.put.mockReset()
    mockAxios.delete.mockReset()
  })

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const store = useAuthStore()

      expect(store._isLoading).toBe(false)
      expect(store._isGoogleLoading).toBe(false)
      expect(store._showAuthModal).toBe(false)
      expect(store._authModalTab).toBe('login')
      expect(store._redirectPath).toBeNull()
    })
  })

  describe('Getters', () => {
    it('isAuthenticated should return false when no user', () => {
      const store = useAuthStore()
      expect(store.isAuthenticated).toBe(false)
    })

    it('isLoading getter should return state value', () => {
      const store = useAuthStore()
      expect(store.isLoading).toBe(false)

      store._isLoading = true
      expect(store.isLoading).toBe(true)
    })

    it('showAuthModal getter should return state value', () => {
      const store = useAuthStore()
      expect(store.showAuthModal).toBe(false)
    })

    it('authModalTab getter should return state value', () => {
      const store = useAuthStore()
      expect(store.authModalTab).toBe('login')
    })
  })

  describe('postAuthRegister', () => {
    it('should store token and user on successful registration', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: mockAuthResponse })

      const store = useAuthStore()
      const userStore = useUserStore()

      const result = await store.postAuthRegister({
        display_name: 'Test User',
        email: 'test@example.com',
        username: 'testuser',
        password: 'password123',
        password_confirmation: 'password123'
      })

      expect(mockAxios.post).toHaveBeenCalledWith(
        '/auth/register',
        expect.objectContaining({
          display_name: 'Test User',
          email: 'test@example.com',
          username: 'testuser',
          password: 'password123',
          password_confirmation: 'password123'
        })
      )
      expect(result).toEqual(mockAuthResponse)
      expect(userStore.me).toEqual(mockUser)
      expect(store._isLoading).toBe(false)
    })

    it('should throw error on registration failure', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Email already exists' } }
      })

      const store = useAuthStore()

      await expect(
        store.postAuthRegister({
          display_name: 'Test User',
          email: 'test@example.com',
          username: 'testuser',
          password: 'password123',
          password_confirmation: 'password123'
        })
      ).rejects.toThrow()

      expect(store._isLoading).toBe(false)
    })
  })

  describe('postAuthLogin', () => {
    it('should authenticate user on successful login', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: mockAuthResponse })

      const store = useAuthStore()
      const userStore = useUserStore()

      const result = await store.postAuthLogin('test@example.com', 'password123')

      expect(mockAxios.post).toHaveBeenCalledWith(
        '/auth/login',
        expect.objectContaining({
          email: 'test@example.com',
          password: 'password123'
        })
      )
      expect(result).toEqual(mockAuthResponse)
      expect(userStore.me).toEqual(mockUser)
      expect(store._isLoading).toBe(false)
    })

    it('should throw error on login failure', async () => {
      mockAxios.post.mockRejectedValueOnce({
        response: { data: { message: 'Invalid credentials' } }
      })

      const store = useAuthStore()

      await expect(store.postAuthLogin('test@example.com', 'wrongpassword')).rejects.toThrow()

      expect(store._isLoading).toBe(false)
    })
  })

  describe('postAuthLogout', () => {
    it('should clear session on logout', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: { message: 'Logged out' } })

      const store = useAuthStore()

      await store.postAuthLogout()

      expect(store._isLoading).toBe(false)
    })

    it('should still clear local data even if API call fails', async () => {
      mockAxios.post.mockRejectedValueOnce(new Error('Network error'))

      const store = useAuthStore()

      await store.postAuthLogout()

      expect(store._isLoading).toBe(false)
    })
  })

  describe('getMe', () => {
    it('should fetch and store user profile', async () => {
      mockAxios.get.mockResolvedValueOnce({ data: mockUser })

      const store = useAuthStore()
      const userStore = useUserStore()

      const result = await store.getMe()

      expect(mockAxios.get).toHaveBeenCalledWith('/auth/me')
      expect(result).toEqual(mockUser)
      expect(userStore.me).toEqual(mockUser)
      expect(store._isLoading).toBe(false)
    })

    it('should handle error when fetching profile fails', async () => {
      mockAxios.get.mockRejectedValueOnce({
        response: { data: { message: 'Unauthenticated' } }
      })

      const store = useAuthStore()
      const userStore = useUserStore()

      await expect(store.getMe()).rejects.toThrow()

      expect(userStore.me).toEqual({})
      expect(store._isLoading).toBe(false)
    })
  })

  describe('putMe', () => {
    it('should update user profile', async () => {
      const updatedUser = { ...mockUser, display_name: 'Updated Name' }
      mockAxios.put.mockResolvedValueOnce({ data: { user: updatedUser } })

      const store = useAuthStore()
      const userStore = useUserStore()

      const result = await store.putMe({ display_name: 'Updated Name' })

      expect(mockAxios.put).toHaveBeenCalledWith('/auth/me', { display_name: 'Updated Name' })
      expect(result.user).toEqual(updatedUser)
      expect(userStore.me).toEqual(updatedUser)
      expect(store._isLoading).toBe(false)
    })
  })

  describe('putAuthPassword', () => {
    it('should change password successfully', async () => {
      mockAxios.put.mockResolvedValueOnce({ data: { user: mockUser } })

      const store = useAuthStore()

      const result = await store.putAuthPassword({
        current_password: 'oldpassword',
        password: 'newpassword123',
        password_confirmation: 'newpassword123'
      })

      expect(mockAxios.put).toHaveBeenCalledWith('/auth/password', {
        current_password: 'oldpassword',
        password: 'newpassword123',
        password_confirmation: 'newpassword123'
      })
      expect(store._isLoading).toBe(false)
    })

    it('should handle password change error', async () => {
      mockAxios.put.mockRejectedValueOnce({
        response: { data: { message: 'Current password is incorrect' } }
      })

      const store = useAuthStore()

      await expect(
        store.putAuthPassword({
          current_password: 'wrongpassword',
          password: 'newpassword123',
          password_confirmation: 'newpassword123'
        })
      ).rejects.toThrow()

      expect(store._isLoading).toBe(false)
    })
  })

  describe('restoreSession', () => {
    it('should return false when no stored user data', () => {
      const store = useAuthStore()

      const result = store.restoreSession()

      expect(result).toBe(false)
    })
  })

  describe('Auth Modal', () => {
    it('openAuthModal should open modal with default tab', () => {
      const store = useAuthStore()

      store.openAuthModal()

      expect(store._showAuthModal).toBe(true)
      expect(store._authModalTab).toBe('login')
    })

    it('openAuthModal should open modal with specified tab', () => {
      const store = useAuthStore()

      store.openAuthModal('register')

      expect(store._showAuthModal).toBe(true)
      expect(store._authModalTab).toBe('register')
    })

    it('closeAuthModal should close modal', () => {
      const store = useAuthStore()
      store._showAuthModal = true

      store.closeAuthModal()

      expect(store._showAuthModal).toBe(false)
    })
  })

  describe('Redirect Path', () => {
    it('setRedirectPath should set redirect path', () => {
      const store = useAuthStore()

      store.setRedirectPath('/profile')

      expect(store._redirectPath).toBe('/profile')
    })

    it('clearRedirectPath should clear redirect path', () => {
      const store = useAuthStore()
      store._redirectPath = '/profile'

      store.clearRedirectPath()

      expect(store._redirectPath).toBeNull()
    })
  })

  describe('postAuthGoogle', () => {
    it('should authenticate with Google on success', async () => {
      mockAxios.post.mockResolvedValueOnce({ data: mockAuthResponse })

      const store = useAuthStore()
      const userStore = useUserStore()

      const result = await store.postAuthGoogle('google-id-token')

      expect(mockAxios.post).toHaveBeenCalledWith(
        '/auth/google',
        expect.objectContaining({
          id_token: 'google-id-token'
        })
      )
      expect(result).toEqual(mockAuthResponse)
      expect(userStore.me).toEqual(mockUser)
      expect(store._isGoogleLoading).toBe(false)
    })
  })

  describe('deleteMe', () => {
    it('should delete user account', async () => {
      mockAxios.delete.mockResolvedValueOnce({ data: {} })

      const store = useAuthStore()

      await store.deleteMe()

      expect(mockAxios.delete).toHaveBeenCalledWith('/auth/me')
      expect(store._isLoading).toBe(false)
    })
  })
})
