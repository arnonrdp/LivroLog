import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store/index";
import { createI18n } from "vue-i18n/index";
import { messages } from "./translation";
import { firebaseConfig } from "./firebase";
import { initializeApp } from "firebase/app";
import VueCustomTooltip from "@adamdehaven/vue-custom-tooltip";

// Initialize Firebase
initializeApp(firebaseConfig);

// Create i18n instance with options
const i18n = createI18n({
  locale: "English",
  fallbackLocale: ["Português", "日本語"],
  messages,
});

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
