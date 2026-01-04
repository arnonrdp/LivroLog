import { useAuthStore, useUserStore } from '@/stores'
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { LocalStorage } from 'quasar'

const routes: Array<RouteRecordRaw> = [
  // Public routes
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/LandingView.vue')
  },
  {
    path: '/search',
    name: 'search',
    component: () => import('@/views/SearchView.vue')
  },
  {
    path: '/books/:bookId',
    name: 'book',
    component: () => import('@/views/BookView.vue'),
    props: true
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/ResetPasswordView.vue')
  },

  // Authenticated routes
  {
    path: '/feed',
    name: 'feed',
    component: () => import('@/views/FeedView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/home',
    name: 'home',
    component: () => import('@/views/HomeView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/add',
    name: 'add',
    component: () => import('@/views/AddView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/people',
    name: 'people',
    component: () => import('@/views/PeopleView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/settings',
    redirect: '/settings/books'
  },
  {
    path: '/settings/:tab',
    name: 'settings',
    component: () => import('@/views/SettingsView.vue'),
    meta: { requiresAuth: true }
  },

  // Legacy redirect: old /login route → landing page
  {
    path: '/login',
    redirect: '/'
  },

  // Public user profiles (must be after specific routes to avoid conflicts)
  {
    path: '/:username',
    name: 'person',
    component: () => import('@/views/PersonView.vue'),
    props: true
  },

  // Catch-all redirect
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()
  const userStore = useUserStore()

  // Check if we have a user loaded and a valid token
  const hasUser = Boolean(userStore.me.id)
  const hasToken = Boolean(LocalStorage.getItem('access_token'))

  // Only call /me if we have a token but no user data loaded
  if (!hasUser && hasToken) {
    try {
      // Try to restore session from server
      await authStore.getMe()
    } catch (error) {
      console.error('Failed to restore session:', error)
      // Clear invalid auth data
      LocalStorage.remove('access_token')
      LocalStorage.remove('user')
      authStore.$reset()
    }
  } else if (!hasUser && !hasToken) {
    // No token and no user - try to restore from localStorage first
    const restored = authStore.restoreSession()
    if (!restored) {
      // Clear any stale data
      authStore.$reset()
    }
  }

  // Re-check authentication status after potential restoration
  const isAuthenticated = authStore.isAuthenticated

  // Only block access for routes that require authentication
  if (to.meta.requiresAuth && !isAuthenticated) {
    // Store the intended destination for redirect after login
    authStore.setRedirectPath(to.fullPath)
    // Open auth modal instead of redirecting (handled by the component)
    authStore.openAuthModal('login')
    // Stay on current page or go to landing if no current page
    if (_from.name) {
      next(false)
    } else {
      next({ path: '/' })
    }
  } else {
    next()
  }
})

export default router
