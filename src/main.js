import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store/index";
import { initializeApp } from "firebase/app";

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

createApp(App)
  .use(router)
  .use(store)
  .mount("#app");
