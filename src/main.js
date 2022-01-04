import VueCustomTooltip from "@adamdehaven/vue-custom-tooltip";
import axios from "axios";
import { Quasar } from "quasar";
import { createApp } from "vue";
import VueAxios from "vue-axios";
import App from "./App.vue";
import { i18n } from "./i18n/index";
import quasarUserOptions from "./quasar-user-options";
import router from "./router";
import store from "./store/index";

createApp(App)
  .use(Quasar, quasarUserOptions)
  .use(router)
  .use(store)
  .use(VueAxios, axios)
  .use(i18n)
  .use(VueCustomTooltip)
  .mount("#app");
