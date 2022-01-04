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
