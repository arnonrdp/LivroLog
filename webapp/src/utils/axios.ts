import axios from 'axios'
import { LocalStorage } from 'quasar'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
})

// Add Authorization header if token exists
api.interceptors.request.use(
  (config) => {
    const token = LocalStorage.getItem('access_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // Remove Content-Type header for FormData to let browser set it with boundary
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
    }

    return config
  },
  (error) => Promise.reject(error)
)

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Check if we already cleared the token to avoid multiple redirects
      const hadToken = Boolean(LocalStorage.getItem('access_token'))

      // Clear all auth data
      LocalStorage.remove('user')
      LocalStorage.remove('access_token')

      // Clear auth store to prevent redirect loop
      const authStore = localStorage.getItem('auth')
      if (authStore) {
        try {
          const authData = JSON.parse(authStore)
          authData._user = {}
          localStorage.setItem('auth', JSON.stringify(authData))
        } catch {
          localStorage.removeItem('auth')
        }
      }

      // Clear user store to ensure isAuthenticated returns false
      const userStore = localStorage.getItem('user')
      if (userStore) {
        try {
          const userData = JSON.parse(userStore)
          userData._me = {}
          localStorage.setItem('user', JSON.stringify(userData))
        } catch {
          localStorage.removeItem('user')
        }
      }

      // Public routes that don't require authentication
      const publicRoutes = ['/login', '/reset-password']
      const isPublicUserProfile = /^\/[a-zA-Z0-9_-]+$/.test(window.location.pathname)
      const isCurrentlyOnPublicRoute = publicRoutes.includes(window.location.pathname) || isPublicUserProfile

      // Only redirect if we had a token and not already on a public route
      // This prevents redirect loops when the 401 is expected (no token)
      if (hadToken && !isCurrentlyOnPublicRoute) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
