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
    meta: { requiresAuth: true, title: "Início" },
  },
  {
    path: "/add",
    name: "Adicionar",
    component: Add,
    meta: { requiresAuth: true, title: "Adicionar" },
  },
  {
    path: "/friends",
    name: "Amigos",
    component: Friends,
    meta: { requiresAuth: true, title: "Amigos" },
  },
  {
    path: "/settings",
    name: "Ajustes",
    component: Settings,
    meta: { requiresAuth: true, title: "Ajustes" },
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
  document.title = `${TITLE} | ${to.meta.title}` || TITLE;
});

export default router;
