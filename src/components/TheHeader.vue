<template>
  <h1>Estante de {{ username }}</h1>
  <div id="nav">
    <router-link to="/"><img src="@/assets/books.svg"/><br>Início</router-link>
    <router-link to="/add"><img src="@/assets/search.svg"/><br>Adicionar</router-link>
    <router-link to="/friends"><img src="@/assets/people.svg"/><br>Amigos</router-link>
    <router-link to="/settings"><img src="@/assets/settings.svg"/><br>Ajustes</router-link>
  </div>
</template>

<script>
import { getAuth, onAuthStateChanged } from "firebase/auth";

export default {
  name: "Header",
  data: () => ({
    username: "",
  }),
  created() {
    const auth = getAuth();
    onAuthStateChanged(auth, (user) => {
      if (user) this.username = user.displayName;
    });
  },
};
</script>

<style scoped>
h1 {
  border: 0.5px solid transparent;
  border-radius: 18px;
  box-shadow: var(--high-shadow);
  font-size: 1.8rem;
  font-weight: 400;
  letter-spacing: 1px;
  margin: auto;
  padding: 10px 30px;
  width: fit-content;
}

#nav {
  display: flex;
  flex-flow: row wrap;
  justify-content: space-evenly;
  padding: 15px;
}

#nav a {
  border: 0.5px solid transparent;
  border-radius: 18px;
  box-shadow: var(--high-shadow);
  color: var(--link-color);
  display: block;
  font-size: 0.8rem;
  font-weight: 500;
  margin: 10px;
  min-width: 100px;
  padding: 10px;
  text-decoration: none;
}

#nav a.router-link-exact-active,
#nav a:hover {
  background-color: #dee3e6;
  box-shadow: var(--low-shadow);
}

#nav img {
  height: 18px;
}
</style>
