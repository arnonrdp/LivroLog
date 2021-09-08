<template>
  <div class="container">
    <header>
      <h1>UM LUGAR PRA VOCÊ ORGANIZAR<br />TUDO AQUILO QUE VOCÊ JÁ LEU</h1>
    </header>
    <main>
      <div class="menu">
        <Button
          text="Entrar"
          @click="activetab = '1'"
          :class="activetab === '1' ? 'active' : ''"
        />
        <Button
          text="Registrar"
          @click="activetab = '2'"
          :class="activetab === '2' ? 'active' : ''"
        />
      </div>
      <form v-if="activetab === '1'" action="#" @submit.prevent="submit">
        <Input
          v-model="email"
          type="email"
          placeholder="E-mail"
          autofocus
          required
        />
        <Input
          v-model="password"
          type="password"
          placeholder="Senha"
          autocomplete
          required
        />
        <Button text="Login" @click="login" />
      </form>

      <form v-if="activetab === '2'" action="#" @submit.prevent="submit">
        <Input v-model="createName" type="text" placeholder="Nome" />
        <Input
          v-model="createEmail"
          type="email"
          placeholder="E-mail"
          required
        />
        <Input
          v-model="createPassword"
          type="password"
          placeholder="Senha"
          autocomplete
          required
        />
        <Button text="Registrar" @click="signUp" />
      </form>
      <hr />
      <p>Entrar com:</p>
      <Button img="google" @click="googleSignIn">
        <img src="/google.svg" alt="" />
      </Button>
    </main>
  </div>
</template>

<script>
import {
  getAuth,
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  getAdditionalUserInfo,
  GoogleAuthProvider,
  signInWithPopup,
} from "firebase/auth";
import { getFirestore, doc, setDoc } from "firebase/firestore";
import Input from "@/components/BaseInput.vue";
import Button from "@/components/BaseButton.vue";

export default {
  name: "Login",
  components: { Input, Button },
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
    login() {
      const auth = getAuth();
      signInWithEmailAndPassword(auth, this.email, this.password).then(
        (userCredential) => {
          const user = userCredential.user;
          alert("Usuário autenticado: " + user);
          this.$router.push("/");
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
    googleSignIn() {
      const auth = getAuth();
      const provider = new GoogleAuthProvider();
      const db = getFirestore();
      signInWithPopup(auth, provider)
        .then(async (result) => {
          // Check if user is new
          const { isNewUser } = getAdditionalUserInfo(result);
          const userId = result.user.uid;
          if (isNewUser) {
            console.log(isNewUser);
            await setDoc(doc(db, "users", userId), {
              email: result.user.email,
              name: result.user.displayName,
            });
          }
          this.$router.push("/");
        })
        .catch((error) => {
          switch (error.code) {
            case "auth/popup-closed-by-user":
              alert("Acho que você fechou o popup.\nTente de novo.");
              break;
            default:
              alert("Algo de errado não está certo:\n" + error.code);
              console.log(error);
          }
        });
    },
    signUp() {
      const auth = getAuth();
      const db = getFirestore();
      createUserWithEmailAndPassword(
        auth,
        this.createEmail,
        this.createPassword
      ).then(
        async (userCredential) => {
          const userId = userCredential.user.uid;
          console.log(userCredential);
          await setDoc(doc(db, "users", userId), {
            email: this.createEmail,
            name: this.createName,
          });
          this.$router.push("/");
          // TODO: REMOVER ALERT E INSERIR MENSAGEM PERSONALIZADA
          alert("Sua conta foi criada com sucesso!");
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
  background-image: url("../assets/bg_login.jpg");
  background-position: top center;
  background-repeat: no-repeat;
  background-size: cover;
  font-family: "SF Pro", sans-serif;
  height: 100vh;
  text-align: center;
}

header {
  padding: 3em 0;
}

header h1 {
  mix-blend-mode: soft-light;
}

main {
  background-color: var(--primary-bg);
  border-radius: 6px;
  margin: auto;
  padding: 2em 1em;
  user-select: none;
  width: 20em;
}

.menu {
  padding: 0 10px 20px;
  display: flex;
}

.menu button {
  width: 100%;
  margin: 0 15px;
}

button:hover img {
  transform: scale(0.95);
}
</style>
