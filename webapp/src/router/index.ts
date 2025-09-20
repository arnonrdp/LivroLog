import { useAuthStore, useUserStore } from '@/stores'
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

  // If no user loaded, try to restore session via /auth/me (cookie-based)
  const hasUser = Boolean(userStore.me.id)
  if (!hasUser) {
    try {
      // Get user data including books in one call
      await authStore.getMe()
      // userStore.me is already populated by authStore.getMe(), no need for additional call
    } catch (error) {
      console.error('Failed to restore session:', error)
      // Clear store to ensure clean state
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
