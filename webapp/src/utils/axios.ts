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

// No Authorization header; use HTTP-only cookies (Sanctum stateful)
api.interceptors.request.use(
  (config) => config,
  (error) => Promise.reject(error)
)

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Clear all auth data (session-based)
      LocalStorage.remove('user')

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

      // Only redirect if not already on login page
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
