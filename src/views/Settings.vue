<template>
  <Header />
  <h1>Definições</h1>
  <button @click="logout">SAIR</button>
</template>

<script>
import { getAuth, signOut } from "firebase/auth";

import Header from "@/components/TheHeader.vue";

export default {
  name: "Settings",
  components: { Header },
  methods: {
    logout() {
      const auth = getAuth();
      signOut(auth).then(() => {
        this.$router.push({name: "Login"});
      });
    },
  },
};
</script>
