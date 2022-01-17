<template>
  <header v-if="this.$route.path !== '/login'">
    <q-toolbar-title class="non-selectable">
      <router-link to="/"><img src="/logo.svg" alt="Logotipo" /></router-link>
      <q-badge color="red" align="top">Beta</q-badge>
    </q-toolbar-title>

    <q-tabs active-color="primary">
      <q-route-tab icon="img:/books.svg" to="/" />
      <q-route-tab icon="search" to="/add" exact />
      <q-route-tab icon="people" to="/people" exact />
      <q-route-tab icon="settings" to="/settings" exact />
    </q-tabs>
  </header>
</template>

<script>
export default { name: "Header" };
</script>

<style scoped>
header {
  align-items: center;
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
  img[alt="Logotipo"] {
    width: 15rem !important;
  }
}

img[alt="Logotipo"] {
  padding: 0 1rem;
  width: 13rem;
}
</style>
