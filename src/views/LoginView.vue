<template>
  <q-page padding>
    <div class="container">
      <LoginForm class="the-form" />
    </div>
    <h3 class="text-primary mobile-hide q-my-lg">A place for you to organize everything you've read.</h3>
    <ShowcaseBooks />
    <h3 class="text-primary mobile-hide bottom-description">Add find out what your friends have read.</h3>
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
  height: 100vh;
  text-align: center;
  padding: 0;
  overflow: hidden;
}

.bottom-description {
  background-color: var(--q-background);
  bottom: 0;
  margin: 0;
  padding: 1.5rem;
  position: absolute;
  width: 100%;
}

.container {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 2;
}

.the-form {
  position: relative;
  padding: 1.5rem;
}
</style>
