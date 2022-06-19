<template>
  <q-page padding>
    <LoginForm />
  </q-page>
</template>

<script setup lang="ts">
import LoginForm from '@/components/LoginForm.vue'
import { defineComponent } from 'vue'

defineComponent({ LoginForm })
</script>

<style scoped>
.q-page {
  background-image: url('@/assets/bg_login.jpg');
  background-position: top center;
  background-repeat: no-repeat;
  background-size: cover;
  font-family: 'SF Pro', sans-serif;
  height: 100vh;
  text-align: center;
}
</style>
