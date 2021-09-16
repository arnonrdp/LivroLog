import { createApp } from "vue";
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

const app = createApp({
  /* your root component */
});

// Instale a instância do store como um plugin
app.use(store);

export default store;
