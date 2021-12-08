import { createStore } from "vuex";

const store = createStore({
  state: {
    user: null,
    count: 0,
  },
  getters: {
    user: (state) => state.user,
  },
  mutations: {
    increment(state) {
      state.count++;
    },
  },
  actions: {
    increment: ({ commit }) => commit("increment"),
  },
});

store.dispatch("increment");

export default store;
