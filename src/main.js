import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import { initializeApp } from "firebase/app";

const firebaseConfig = {
  apiKey: "AIzaSyAJGXLBDW269OHGuSblb0FTg80EmdLLdBQ",
  authDomain: "minha-estante-virtual-314723.firebaseapp.com",
  projectId: "minha-estante-virtual-314723",
  storageBucket: "minha-estante-virtual-314723.appspot.com",
  messagingSenderId: "997565134767",
  appId: "1:997565134767:web:6809fb3b9332953885a15a",
  measurementId: "G-FSNN76QH38",
};

// Initialize Firebase
initializeApp(firebaseConfig);

createApp(App).use(router).mount("#app");
