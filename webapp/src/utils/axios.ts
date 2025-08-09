import axios from 'axios'
import { LocalStorage } from 'quasar'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  }
})

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Clear all auth data
      localStorage.removeItem('auth_token')
      LocalStorage.remove('user')

      // Clear auth store to prevent redirect loop
      const authStore = localStorage.getItem('auth')
      if (authStore) {
        try {
          const authData = JSON.parse(authStore)
          authData._user = {}
          localStorage.setItem('auth', JSON.stringify(authData))
        } catch (e) {
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
