import { createRouter, createWebHistory } from "vue-router";
import store from "../store";
import Add from "../views/Add.vue";
import Friends from "../views/Friends.vue";
import Home from "../views/Home.vue";
import Login from "../views/Login.vue";
import Settings from "../views/Settings.vue";

const routes = [
  {
    path: "/:pathMatch(.*)*",
    name: "NotFound",
    redirect: "/",
  },
  {
    path: "/login",
    name: "Login",
    component: Login,
    meta: { title: "Livrero: Login" },
  },
  {
    path: "/",
    name: "Início",
    component: Home,
    alias: "/home",
    meta: { requiresAuth: true, title: "Livrero" },
  },
  {
    path: "/add",
    name: "Adicionar",
    component: Add,
    meta: { requiresAuth: true, title: "Livrero: Adicionar" },
  },
  {
    path: "/friends",
    name: "Amigos",
    component: Friends,
    meta: { requiresAuth: true, title: "Livrero: Amigos" },
  },
  {
    path: "/settings",
    name: "Ajustes",
    component: Settings,
    meta: { requiresAuth: true, title: "Livrero: Ajustes" },
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
  const DEFAULT_TITLE = "Livrero";
  document.title = to.meta.title || DEFAULT_TITLE;
});

export default router;
