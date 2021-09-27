import { createRouter, createWebHistory } from "vue-router";
import { getAuth, onAuthStateChanged } from "firebase/auth";

import Login from "../views/Login.vue";
import Home from "../views/Home.vue";
import Add from "../views/Add.vue";
import Friends from "../views/Friends.vue";
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
  history: createWebHistory(process.env.BASE_URL),
  routes,
});

router.beforeEach((to, from, next) => {
  const auth = getAuth();

  onAuthStateChanged(auth, (user) => {
    if (to.meta.requiresAuth && user == null) next({ name: "Login" });
    else next();
  });
});

router.afterEach((to, from) => {
  const DEFAULT_TITLE = "Livrero";
  document.title = to.meta.title || DEFAULT_TITLE;
});


export default router;
