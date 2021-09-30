<template>
  <div class="container">
    <header>
      <h1>{{ $t("logintitle1") }}<br />{{ $t("logintitle2") }}</h1>
    </header>
    <main>
      <img src="/logo.svg" alt="logotipo" />
      <div class="menu">
        <Button
          :text="$t('signin')"
          @click="activetab = '1'"
          :class="activetab === '1' ? 'active' : ''"
        />
        <Button
          :text="$t('signup')"
          @click="activetab = '2'"
          :class="activetab === '2' ? 'active' : ''"
        />
      </div>
      <form v-if="activetab === '1'" action="#" @submit.prevent="submit">
        <Input v-model="email" type="email" :label="$t('mail')" />
        <Input
          v-model="password"
          type="password"
          :label="$t('password')"
          autocomplete
        />
        <Button text="Login" @click="login" />
      </form>

      <form v-if="activetab === '2'" action="#" @submit.prevent="submit">
        <Input v-model="createName" type="text" :label="$t('name')" />
        <Input v-model="newEmail" type="email" :label="$t('mail')" />
        <Input v-model="newPass" type="password" :label="$t('password')" autocomplete />
        <Button :text="$t('signup')" @click="signUp" />
      </form>
      <hr />
      <Button img="google" @click="googleSignIn">
        <img src="/google.svg" alt="" />
      </Button>
    </main>
  </div>
</template>

<script>
import { auth, db } from '@/firebase';
import {
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  getAdditionalUserInfo,
  GoogleAuthProvider,
  signInWithPopup,
} from "firebase/auth";
import { doc, setDoc } from "firebase/firestore";
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
    newEmail: "",
    newPass: "",
    errMsg: "",
  }),
  methods: {
    login() {
      signInWithEmailAndPassword(auth, this.email, this.password).then(
        this.$router.push("/"),
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
      const provider = new GoogleAuthProvider();
      signInWithPopup(auth, provider)
        .then(async (result) => {
          // Check if user is new
          const { isNewUser } = getAdditionalUserInfo(result);
          const userId = result.user.uid;
          if (isNewUser) {
            await setDoc(doc(db, "users", userId), {
              email: result.user.email,
              name: result.user.displayName,
              shelfName: result.user.displayName,
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
      createUserWithEmailAndPassword(auth, this.newEmail, this.newPass).then(
        async (userCredential) => {
          const userId = userCredential.user.uid;
          console.log(userCredential);
          await setDoc(doc(db, "users", userId), {
            email: this.newEmail,
            name: this.createName,
            shelfName: this.createName,
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
  padding: 2em 1em 0.5em;
  user-select: none;
  width: 20em;
}

img[alt="logotipo"] {
  margin-bottom: 1.5em;
  width: 15em;
}

.menu {
  display: flex;
}

.menu button {
  margin: 0 1.5em;
  width: 100%;
}
</style>
