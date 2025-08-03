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

  if (!authStore.isAuthenticated) {
    const token = localStorage.getItem('auth_token')
    if (token) {
      try {
        await authStore.getAuthMe()
      } catch (error) {
        console.error('Erro ao restaurar sessão:', error)
        localStorage.removeItem('auth_token')
      }
    }
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ path: '/login' })
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next({ path: '/' })
  } else {
    next()
  }
})

export default router
