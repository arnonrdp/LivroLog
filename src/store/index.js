import { createStore } from "vuex";

const store = createStore({
  state() {
    return {
      count: 0,
    };
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
