<template>
  <q-layout>
    <Header />
    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script>
import Header from "@/components/TheHeader.vue";

export default {
  name: "App",
  components: { Header },
};
</script>

<style>
html {
  font-size: 16px;
}
@media screen and (max-width: 720px) {
  html {
    font-size: 15px;
  }
}
@media screen and (max-width: 640px) {
  html {
    font-size: 14px;
  }
}
@media screen and (max-width: 560px) {
  html {
    font-size: 13px;
  }
}
@media screen and (max-width: 480px) {
  html {
    font-size: 12px;
  }
}
@media screen and (max-width: 400px) {
  html {
    font-size: 11px;
  }
}
@media screen and (max-width: 320px) {
  html {
    font-size: 10px;
  }
}
@media screen and (max-width: 240px) {
  html {
    font-size: 9px;
  }
}

body {
  margin: 0;
  background-color: var(--primary-bg);
}

#app {
  color: var(--primary-text);
  font-family: "Helvetica Neue", "Helvetica", "Arial", sans-serif;
  text-align: center;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
</style>
