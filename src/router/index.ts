import { useUserStore } from '@/store'
import AddView from '@/views/AddView.vue'
import HomeView from '@/views/HomeView.vue'
import LoginView from '@/views/LoginView.vue'
import PeopleView from '@/views/PeopleView.vue'
import SettingsAccount from '@/views/SettingsAccount.vue'
import SettingsBooks from '@/views/SettingsBooks.vue'
import SettingsProfile from '@/views/SettingsProfile.vue'
import SettingsView from '@/views/SettingsView.vue'
import PersonView from '@/views/PersonView.vue'
import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'

const routes: Array<RouteRecordRaw> = [
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  },
  {
    path: '/login',
    component: LoginView
  },
  {
    path: '/',
    alias: '/home',
    component: HomeView,
    meta: { requiresAuth: true }
  },
  {
    path: '/add',
    component: AddView,
    meta: { requiresAuth: true }
  },
  {
    path: '/people',
    component: PeopleView,
    meta: { requiresAuth: true }
  },
  {
    path: '/:username',
    name: 'user',
    component: PersonView,
    props: true
  },
  {
    path: '/settings',
    component: SettingsView,
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: 'account' },
      { path: 'account', component: SettingsAccount },
      { path: 'books', component: SettingsBooks },
      { path: 'profile', component: SettingsProfile }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
})

router.beforeEach(async (to, _from, next) => {
  const userStore = useUserStore()

  if (to.meta.requiresAuth && !userStore.getUser.uid) next({ path: '/login' })
  else next()
})

export default router
