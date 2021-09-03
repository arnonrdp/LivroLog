<template>
  <div class="container">
    <header>
      <h1>UM LUGAR PRA VOCÊ ORGANIZAR<br />TUDO AQUILO QUE VOCÊ JÁ LEU</h1>
    </header>
    <main>
      <ul>
        <li>
          <a @click="activetab = '1'" :class="activetab === '1' ? 'active' : ''"
            >Entrar</a
          >
        </li>
        <li>
          <a @click="activetab = '2'" :class="activetab === '2' ? 'active' : ''"
            >Registrar</a
          >
        </li>
      </ul>

      <form v-if="activetab === '1'" action="/login">
        <input
          v-model="email"
          type="email"
          placeholder="E-mail"
          autofocus
          required
        />
        <input
          v-model="password"
          type="password"
          placeholder="Senha"
          autocomplete
          required
        />
        <button @click="login">Login</button>
      </form>

      <form v-if="activetab === '2'">
        <input v-model="createName" type="text" placeholder="Nome" />
        <input
          v-model="createEmail"
          type="email"
          placeholder="E-mail"
          required
        />
        <input
          v-model="createPassword"
          type="password"
          placeholder="Senha"
          autocomplete
          required
        />
        <button @click="signUp">Registrar</button>
      </form>
      <button @click="googleSignIn">Entrar com Google</button>
    </main>
  </div>
</template>

<script>
import {
  getAuth,
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  signInWithPopup,
  GoogleAuthProvider,
} from "firebase/auth";

export default {
  name: "Login",
  data: () => ({
    activetab: "1",
    email: "",
    password: "",
    createName: "",
    createEmail: "",
    createPassword: "",
    errMsg: "",
  }),
  methods: {
    login: function() {
      const auth = getAuth();
      signInWithEmailAndPassword(this.email, this.password).then(
        (userCredential) => {
          const user = userCredential.user;
          console.log("Usuário autenticado: " + user);
          this.$router.replace("/");
        },
        (err) => {
          switch (err.code) {
            case "auth/invalid-email":
              this.errMsg = "E-mail inválido";
              break;
            case "auth/user-not-found":
              this.errMsg = "Não encontrei seu usuário";
              break;
            case "auth/wrong-password":
              this.errMsg = "Senha incorreta";
              break;
            default:
              this.errMsg = "E-mail ou senha incorreta";
              break;
          }
        }
      );
    },
    googleSignIn: function() {
      let provider = new GoogleAuthProvider();
      const auth = getAuth();
        signInWithPopup(auth, provider)
        .then((result) => {
          let user = result.user;
          this.$router.replace("/");
          console.log(user); // User that was authenticated
        })
        .catch((err) => {
          console.log(err); // This will give you all the information needed to further debug any errors
        });
    },
    signUp: function() {
      const auth = getAuth();
      createUserWithEmailAndPassword(
        auth,
        this.createEmail,
        this.createPassword
      ).then(
        (userCredential) => {
          const user = userCredential.user;
          this.$router.replace("/"),
            alert("Sua conta foi criada com sucesso!" + user);
        },
        (error) => {
          alert("Aconteceu algo inesperado. Verifique o console");
          console.log(error.code);
          console.log(error.message);
        }
      );
    },
  },
  created() {
    if (window.localStorage.getItem("authenticated") === "true") {
      this.$router.push("/home");
    }
  },
};
</script>

<style scoped>
.container {
  height: 100vh;
  font-family: "SF Pro", sans-serif;
  background-image: url("../assets/bg_login.jpg");
  background-repeat: no-repeat;
  background-size: cover;
  background-position: top center;
  text-align: center;
}

header {
  padding: 3em 0;
}

header h1 {
  mix-blend-mode: soft-light;
}

main {
  width: 20em;
  margin: auto;
  padding: 2em 1em;
  background-color: #e6e7ee;
  border-radius: 6px;
}

ul {
  list-style-type: none;
  margin: 0%;
  padding: 0 10px 15px;
  display: flex;
  justify-content: space-around;
}

ul li {
  width: 100%;
  margin: 0 1vw;
}

ul li a {
  display: block;
  font-size: 80%;
  padding: 8px 0;
  border-radius: 6px;
  border: 0.5px solid transparent;
  box-shadow: 3px 3px 6px #b8b9be, -3px -3px 6px #ffffff;
}

ul li a:hover,
ul li a.active {
  background-color: #dee3e6;
  box-shadow: inset 2px 2px 5px #b8b9be, inset -3px -3px 7px #ffffff;
}

form {
  display: flex;
  flex-direction: column;
  align-items: center;
}

form input {
  width: 20em;
  margin: 5px 0;
  padding: 10px;
  overflow: visible;
  outline: 0;
  border-radius: 18px;
  background-clip: padding-box;
  border: 0.5px solid #d1d9e6;
  background-color: #dee3e6;
  box-shadow: inset 2px 2px 5px #b8b9be, inset -3px -3px 7px #ffffff;
}

form button {
  margin: 5px 0 0;
  padding: 10px;
  border-radius: 6px;
  background-color: #dee3e6;
  border: 0.5px solid transparent;
  box-shadow: 3px 3px 6px #b8b9be, -3px -3px 6px #ffffff;
}

form button:hover {
  box-shadow: inset 2px 2px 5px #b8b9be, inset -3px -3px 7px #ffffff;
}
</style>
