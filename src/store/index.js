import { createApp } from "vue";
import { createStore } from "vuex";

// Cria uma nova instância do store.
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
});

const app = createApp({
  /* your root component */
});

// Instale a instância do store como um plugin
app.use(store);

export default store;