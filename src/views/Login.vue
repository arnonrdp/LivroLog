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

      <form v-if="activetab === '1'" action="#" @submit.prevent="submit">
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

      <form v-if="activetab === '2'" action="#" @submit.prevent="submit">
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
      <hr />
      <p>Entrar com:</p>
      <button class="social" @click="googleSignIn">
        <img src="/google.svg" alt="" />
      </button>
    </main>
  </div>
</template>

<script>
import {
  getAuth,
  setPersistence,
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  GoogleAuthProvider,
  browserSessionPersistence,
  signInWithPopup,
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
      signInWithEmailAndPassword(auth, this.email, this.password).then(
        (userCredential) => {
          const user = userCredential.user;
          alert("Usuário autenticado: " + user);
          this.$router.push("/").catch((error) => {
            console.log(error);
          });
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
      const auth = getAuth();
      const provider = new GoogleAuthProvider();
      signInWithPopup(auth, provider)
        .then(() => {
          this.$router.push("/").catch((error) => {
            console.log(error);
          });
        })
        .catch((error) => {
          switch (error.code) {
            case "auth/popup-closed-by-user":
              alert("Acho que você fechou o popup.\nTente de novo.");
              break;
            default:
              alert("Algo de errado não está certo:\n" + error.code);
          }
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
          this.$router.push("/").catch((error) => {
            console.log(error);
          }),
          // TODO: REMOVER ALERT E INSERIR MENSAGEM PERSONALIZADA
            alert("Sua conta foi criada com sucesso!\nAgora faça login" + user);
        },
        (error) => {
          // TODO: REMOVER ALERTS E INSERIR MENSAGENS PERSONALIZADAS
          alert(error.code);
          alert(error.message);
        }
      );
    },
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
  background-color: var(--primary-bg);
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
  box-shadow: var(--high-shadow);
}

ul li a:hover,
ul li a.active {
  background-color: #dee3e6;
  box-shadow: var(--low-shadow);
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
  box-shadow: var(--low-shadow);
}

form button {
  margin: 5px 0 0;
  padding: 10px;
  border-radius: 6px;
  background-color: #dee3e6;
  border: 0.5px solid transparent;
  box-shadow: var(--high-shadow);
}

form button:hover {
  box-shadow: var(--low-shadow);
}

button.social {
  padding: 10px;
}
</style>
