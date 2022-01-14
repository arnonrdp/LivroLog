<template>
  <header v-if="this.$route.path !== '/login'">
    <router-link to="/"><img src="/logo.svg" alt="Logotipo" /></router-link>
    <q-tabs active-color="primary">
      <q-route-tab icon="img:/books.svg" to="/" />
      <q-route-tab icon="search" to="/add" exact />
      <q-route-tab icon="people" to="/friends" exact />
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
  justify-content: space-between;
  margin-bottom: 25px;
}

@media screen and (max-width: 599px) {
  header {
    display: block;
    padding-top: 15px;
  }
}

@media screen and (max-width: 320px) {
  img[alt="Logotipo"] {
    padding: 0 15px;
    width: 150px !important;
  }
}

@media screen and (max-width: 240px) {
  img[alt="Logotipo"] {
    padding: 0 15px;
    width: 110px !important;
  }
}

img[alt="Logotipo"] {
  padding: 0 15px;
  width: 200px;
}
</style>
