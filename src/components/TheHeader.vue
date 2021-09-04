<template>
  <h1>Estante do {{ username }}</h1>
  <div id="nav">
    <router-link to="/">Início</router-link>
    <router-link to="/add">Adicionar</router-link>
    <router-link to="/friends">Amigos</router-link>
    <router-link to="/settings">Ajustes</router-link>
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
      if (user) {
        this.username = user.displayName;
      } else {
        this.username = "deslogado";
      }
    });
  },
};
</script>

<style scoped>
h1 {
	border: 0.5px solid transparent;
	border-radius: 6px;
	box-shadow: var(--high-shadow);
  font-weight: 400;
	letter-spacing: 1px;
  margin: auto;
  padding: 10px 30px;
  width: fit-content;
}

#nav {
  display: flex;
	justify-content: space-evenly;
  padding: 30px;
}

#nav a {
	border: 0.5px solid transparent;
	border-radius: 6px;
	box-shadow: var(--high-shadow);
  color: #2c3e50;
  display: block;
	font-size: 80%;
  font-weight: 500;
  padding: 10px;
  text-decoration: none;
}

#nav a.router-link-exact-active,
#nav a:hover {
  background-color: #dee3e6;
	box-shadow: var(--low-shadow);
}
</style>
