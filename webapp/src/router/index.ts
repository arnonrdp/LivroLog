import { useAuthStore } from '@/stores'
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

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
    alias: '/home',
    component: () => import('@/views/HomeView.vue'),
    meta: { requiresAuth: true }
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

  // Check if we have a token but no user data (page reload scenario)
  const token = localStorage.getItem('auth_token')
  const hasToken = Boolean(token)
  const hasUser = Boolean(authStore.user.id)

  // If we have a token but no user, try to restore session
  if (hasToken && !hasUser) {
    try {
      await authStore.getAuthMe()
    } catch (error) {
      console.error('Failed to restore session:', error)
      // Clear invalid token
      localStorage.removeItem('auth_token')
      // Clear store to ensure clean state
      authStore.$reset()
    }
  }

  // Re-check authentication status after potential restoration
  const isAuthenticated = authStore.isAuthenticated && hasToken

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ path: '/login' })
  } else if (to.path === '/login' && isAuthenticated) {
    next({ path: '/' })
  } else {
    next()
  }
})

export default router
