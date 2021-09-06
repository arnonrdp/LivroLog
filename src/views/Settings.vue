<template>
  <Header />
  <h1>Definições</h1>
  <Button text="SAIR" @click="logout" />
</template>

<script>
import { getAuth, signOut } from "firebase/auth";
import Header from "@/components/TheHeader.vue";
import Button from '@/components/BaseButton.vue';

export default {
  name: "Settings",
  components: { Header, Button },
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
