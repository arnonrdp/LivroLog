import { createRouter, createWebHistory } from "vue-router";
import { getAuth } from "firebase/auth";

import Home from "../views/Home.vue";
import Buscar from "../views/Buscar.vue";
import Login from "../views/Login.vue";

const routes = [
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    redirect: "/login",
  },
  {
    path: "/login",
    name: "Login",
    component: Login,
  },
  {
    path: "/",
    name: "Home",
    component: Home,
    meta: { requiresAuth: true },
  },
  {
    path: "/buscar",
    name: "Buscar",
    component: Buscar,
    meta: { requiresAuth: true },
  },
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes,
});

router.beforeEach((to, from, next) => {
  const currentUser = getAuth().currentUser;
  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);

  if (requiresAuth && !currentUser) next("login");
  else if (!requiresAuth && currentUser) next("home");
  else next();
});

export default router;
