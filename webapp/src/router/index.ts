import { useAuthStore, useUserStore } from '@/stores'
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { LocalStorage } from 'quasar'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/login',
    component: () => import('@/views/LoginView.vue')
  },
  {
    path: '/reset-password',
    component: () => import('@/views/ResetPasswordView.vue')
  },
  {
    path: '/',
    component: () => import('@/views/HomeView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/home',
    redirect: '/'
  },
  {
    path: '/add',
    component: () => import('@/views/AddView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/people',
    component: () => import('@/views/PeopleView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/:username',
    component: () => import('@/views/PersonView.vue'),
    props: true
  },
  {
    path: '/settings',
    redirect: '/settings/books'
  },
  {
    path: '/settings/:tab',
    component: () => import('@/views/SettingsView.vue'),
    meta: { requiresAuth: true }
  },
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

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ path: '/login' })
  } else if (to.path === '/login' && isAuthenticated) {
    next({ path: '/' })
  } else {
    next()
  }
})

export default router
