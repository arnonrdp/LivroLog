import { createRouter, createWebHistory } from "vue-router";
import { useI18n } from "vue-i18n";
import store from "../store";
import Add from "../views/Add.vue";
import Home from "../views/Home.vue";
import Login from "../views/Login.vue";
import People from "../views/People.vue";
import Settings from "../views/Settings.vue";
import SettingsAccount from "../views/SettingsAccount.vue";
import SettingsBooks from "../views/SettingsBooks.vue";
import SettingsProfile from "../views/SettingsProfile.vue";
import User from "../views/User.vue";

const routes = [
  {
    path: "/:pathMatch(.*)*",
    redirect: "/",
  },
  {
    path: "/login",
    component: Login,
  },
  {
    path: "/",
    component: Home,
    alias: "/home",
    meta: { requiresAuth: true, title: "Início" },
  },
  {
    path: "/add",
    component: Add,
    meta: { requiresAuth: true, title: "Adicionar" },
  },
  {
    path: "/people",
    component: People,
    meta: { requiresAuth: true, title: "Friends" },
  },
  {
    path: "/:username",
    name: "user",
    component: User,
    props: true,
  },
  {
    path: "/settings",
    component: Settings,
    meta: { requiresAuth: true, title: "Ajustes" },
    children: [
      { path: "", redirect: "account" },
      { path: "account", component: SettingsAccount },
      { path: "books", component: SettingsBooks },
      { path: "profile", component: SettingsProfile },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from, next) => {
  let isAuthenticated = store.getters.isAuthenticated;
  if (to.meta.requiresAuth && !isAuthenticated) next("login");
  else next();
});

router.afterEach((to, from) => {
  const TITLE = "Livrero";
  document.title = !to.meta.title ? TITLE : `${TITLE} | ${to.meta.title}`;
});

export default router;
