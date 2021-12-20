import VueCustomTooltip from "@adamdehaven/vue-custom-tooltip";
import { createApp } from "vue";
import App from "./App.vue";
import { i18n } from "./locale/translation.ts";
import router from "./router";
import store from "./store/index";

createApp(App)
  .use(router)
  .use(store)
  .use(i18n)
  .use(VueCustomTooltip)
  .mount("#app");
