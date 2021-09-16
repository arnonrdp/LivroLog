import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store/index";
import { i18n } from "./translation.ts";
import VueCustomTooltip from "@adamdehaven/vue-custom-tooltip";

const opt = {
  name: "Tooltip",
  borderRadius: 4,
};

createApp(App)
  .use(router)
  .use(store)
  .use(i18n)
  .use(VueCustomTooltip, opt)
  .mount("#app");
