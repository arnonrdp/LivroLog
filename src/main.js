import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store/index";
import { createI18n } from "vue-i18n/index";
import { messages } from "./translation";
import { initializeApp } from "firebase/app";
import { getAuth, signInWithEmailAndPassword } from "firebase/auth";
import VueCustomTooltip from "@adamdehaven/vue-custom-tooltip";

const firebaseConfig = {
  apiKey: "AIzaSyDKfpzOLiaER6Q89HTr-AMO4mAT5EByx2o",
  authDomain: "livrero-app.firebaseapp.com",
  projectId: "livrero-app",
  storageBucket: "livrero-app.appspot.com",
  messagingSenderId: "599345136110",
  appId: "1:599345136110:web:f75a7d5be9c971f248ceff",
  measurementId: "G-KR0XXDXD27",
};

// Initialize Firebase
initializeApp(firebaseConfig);

const auth = getAuth();
export { auth, signInWithEmailAndPassword };

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
