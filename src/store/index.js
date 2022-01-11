import { doc, updateDoc } from "firebase/firestore";
import SecureLS from "secure-ls";
import { createStore } from "vuex";
import createPersistedState from "vuex-persistedstate";
import { db } from "../firebase";
import authentication from "./modules/authentication";
import bookstore from "./modules/bookstore";
import people from "./modules/people";

const ls = new SecureLS({ isCompression: false });

const store = createStore({
  state: {
    modifiedAt: null,
  },
  getters: {
    getModifiedAt(state) {
      return state.modifiedAt;
    },
  },
  mutations: {
    setModifiedAt(state, val) {
      state.modifiedAt = val;
    },
  },
  actions: {
    async modifiedAt({ commit, rootGetters }) {
      const currentDate = Date.now();
      await updateDoc(doc(db, "users", rootGetters.getUserID), { modifiedAt: currentDate })
        .then(() => commit("setModifiedAt", currentDate))
        .catch((error) => console.error(error));
    },
  },
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
  modules: { authentication, bookstore, people },
});

export default store;
