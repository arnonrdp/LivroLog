import { useUserStore } from '@/store'
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  },
  {
    path: '/login',
    component: () => import('@/views/LoginView.vue')
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
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

router.beforeEach(async (to, _from, next) => {
  const userStore = useUserStore()

  if (to.meta.requiresAuth && !userStore.getUser.uid) next({ path: '/login' })
  else next()
})

export default router
