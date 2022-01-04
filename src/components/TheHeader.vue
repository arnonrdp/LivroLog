<template>
  <header v-if="this.$route.path !== '/login'">
    <router-link to="/"><img src="/logo.svg" alt="Logotipo"/></router-link>
    <nav>
      <router-link to="/">
        <img src="@/assets/books.svg" />&nbsp;{{ $t("menu.home") }}
      </router-link>
      <router-link to="/add">
        <img src="@/assets/search.svg" />&nbsp;{{ $t("menu.add") }}
      </router-link>
      <router-link to="/friends">
        <img src="@/assets/people.svg" />&nbsp;{{ $t("menu.friends") }}
      </router-link>
      <router-link to="/settings">
        <img src="@/assets/settings.svg" />&nbsp;{{ $t("menu.settings") }}
      </router-link>
    </nav>
  </header>
</template>

<script>
export default {
  name: "Header",
};
</script>

<style scoped>
header {
  align-items: center;
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05),
    inset 0 -1px 0 rgba(0, 0, 0, 0.1);
  display: flex;
  justify-content: space-between;
  margin-bottom: 25px;
}

@media screen and (max-width: 720px) {
  header {
    display: block;
    margin-top: 15px;
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

nav {
  display: flex;
  flex-flow: row wrap;
  justify-content: space-evenly;
  padding: 0 15px;
}

nav a {
  align-items: center;
  color: #000;
  display: flex;
  font-size: 0.8rem;
  height: 30px;
  margin: 10px;
  padding: 5px 10px;
  text-decoration: none;
}

nav a.router-link-exact-active,
nav a:hover {
  outline: 1px solid rgba(0, 0, 0, 0.4);
}

nav img {
  height: 1rem;
}
</style>
