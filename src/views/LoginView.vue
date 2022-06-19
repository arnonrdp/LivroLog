<template>
  <q-page>
    <div class="container">
      <LoginForm class="the-form" />
    </div>
    <div class="text-primary window-height">
      <h3>{{ $t('sign.description-top') }}</h3>
      <ShowcaseBooks />
      <h3 class="q-my-none q-pb-xl">{{ $t('sign.description-bottom') }}</h3>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import LoginForm from '@/components/LoginForm.vue'
import ShowcaseBooks from '@/components/ShowcaseBooks.vue'
import { defineComponent } from 'vue'

defineComponent({ LoginForm, ShowcaseBooks })
</script>

<style scoped>
.q-page {
  font-family: 'SF Pro', sans-serif;
  text-align: center;
}

.container {
  background-image: url('@/assets/bg_login.jpg');
  background-position: top center;
  background-repeat: no-repeat;
  background-size: cover;
  height: 100vh;
  position: relative;
}

.the-form {
  left: 50%;
  padding: 1.5rem;
  position: absolute;
  top: 50%;
  transform: translate(-50%, -50%);
}
</style>
