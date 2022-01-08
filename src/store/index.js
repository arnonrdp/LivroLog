import SecureLS from "secure-ls";
import { createStore } from "vuex";
import createPersistedState from "vuex-persistedstate";
import authentication from "./modules/authentication";
import bookstore from "./modules/bookstore";

const ls = new SecureLS({ isCompression: false });

const store = createStore({
  state: {},
  getters: {},
  mutations: {},
  actions: {},
  modules: { authentication, bookstore },
  plugins: [
    createPersistedState({
      // TODO: Descomentar em produção
      // storage: {
      //   getItem: (key) => ls.get(key),
      //   setItem: (key, value) => ls.set(key, value),
      //   removeItem: (key) => ls.remove(key),
      // },
    }),
  ],
});

export default store;
