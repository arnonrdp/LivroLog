import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store/index";
import { i18n } from "./translation.ts";
import VueCustomTooltip from "@adamdehaven/vue-custom-tooltip";

createApp(App)
  .use(router)
  .use(store)
  .use(i18n)
  .use(VueCustomTooltip)
  .mount("#app");
