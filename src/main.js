import VueCustomTooltip from "@adamdehaven/vue-custom-tooltip";
import axios from "axios";
import { createApp } from "vue";
import VueAxios from "vue-axios";
import App from "./App.vue";
import { i18n } from "./locale/translation.js";
import router from "./router";
import store from "./store/index";

createApp(App)
  .use(router)
  .use(store)
  .use(VueAxios, axios)
  .use(i18n)
  .use(VueCustomTooltip)
  .mount("#app");
