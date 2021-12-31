import SecureLS from "secure-ls";
import { createStore } from "vuex";
import createPersistedState from "vuex-persistedstate";
import authentication from "./modules/authentication";
import bookstore from "./modules/bookstore";

const ls = new SecureLS({ isCompression: false });

const store = createStore({
  state: {
    error: null,
    information: null,
    loading: false,
  },
  plugins: [
    createPersistedState({
      // storage: {
      //   getItem: (key) => ls.get(key),
      //   setItem: (key, value) => ls.set(key, value),
      //   removeItem: (key) => ls.remove(key),
      // },
    }),
  ],
  getters: {
    getError(state) {
      return state.error;
    },
    getInformation(state) {
      return state.information;
    },
    getLoading(state) {
      return state.loading;
    },
  },
  mutations: {
    setError(state, payload) {
      state.error = payload;
    },
    setInformation(state, payload) {
      state.information = payload;
    },
    setLoading(state, payload) {
      state.loading = payload;
    },
  },
  actions: {},
  modules: { authentication, bookstore },
});

export default store;
