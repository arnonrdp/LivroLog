import { createRouter, createWebHistory } from "vue-router";
import { i18n } from "../i18n/index";
import store from "../store";
import Add from "../views/Add.vue";
import Home from "../views/Home.vue";
import Login from "../views/Login.vue";
import People from "../views/People.vue";
import Settings from "../views/Settings.vue";
import SettingsAccount from "../views/SettingsAccount.vue";
import SettingsBooks from "../views/SettingsBooks.vue";
import User from "../views/User.vue";

const routes = [
  {
    path: "/:pathMatch(.*)*",
    redirect: "/",
  },
  {
    path: "/login",
    component: Login,
    meta: { title: "Livrero: Login" },
  },
  {
    path: "/",
    component: Home,
    alias: "/home",
    meta: { requiresAuth: true, title: i18n.global.t("menu.home") },
  },
  {
    path: "/add",
    component: Add,
    meta: { requiresAuth: true, title: i18n.global.t("menu.add") },
  },
  {
    path: "/people",
    component: People,
    meta: { requiresAuth: true, title: i18n.global.t("menu.friends") },
  },
  {
    path: "/:username",
    name: "user",
    component: User,
    props: true,
    meta: { requiresAuth: false },
  },
  {
    path: "/settings",
    component: Settings,
    meta: { requiresAuth: true, title: i18n.global.t("menu.settings") },
    children: [
      { path: "", redirect: "account" },
      { path: "account", component: SettingsAccount },
      { path: "books", component: SettingsBooks },
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
