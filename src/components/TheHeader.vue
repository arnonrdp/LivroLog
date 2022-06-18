<template>
  <header v-if="$route.path !== '/login'">
    <q-toolbar-title class="non-selectable">
      <router-link to="/"><img src="/logo.svg" alt="Logotipo" /></router-link>
      <q-badge color="red" align="top">Beta</q-badge>
    </q-toolbar-title>

    <q-tabs active-color="primary">
      <q-route-tab icon="img:/books.svg" to="/" />
      <q-route-tab icon="search" to="/add" exact />
      <q-route-tab icon="people" to="/people" exact />
      <q-btn-dropdown auto-close stretch flat icon="settings">
        <q-list>
          <q-item clickable to="/settings/books">
            <q-item-section>{{ $t('settings.add-reading-dates') }}</q-item-section>
          </q-item>
          <q-item clickable to="/settings/profile">
            <q-item-section>{{ $t('settings.profile') }}</q-item-section>
          </q-item>
          <q-item clickable to="/settings/account">
            <q-item-section>{{ $t('settings.account') }}</q-item-section>
          </q-item>
          <q-separator />
          <q-item clickable to="/login" @click="logout()">
            <q-item-section>{{ $t('sign.logout') }}</q-item-section>
          </q-item>
        </q-list>
      </q-btn-dropdown>
    </q-tabs>
  </header>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/store'

const authStore = useAuthStore()

async function logout() {
  authStore.logout()
}
</script>

<style scoped>
header {
  align-items: baseline;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05), inset 0 -1px 0 rgba(0, 0, 0, 0.1);
  display: flex;
  text-align: left;
}

@media screen and (max-width: 599px) {
  header {
    display: block;
    padding-top: 1rem;
    text-align: center;
  }
}

@media screen and (max-width: 320px) {
  img[alt='Logotipo'] {
    width: 15rem !important;
  }
}

img[alt='Logotipo'] {
  padding: 0 1rem;
  width: 13rem;
}

.q-btn-dropdown {
  opacity: 0.85;
}
</style>
