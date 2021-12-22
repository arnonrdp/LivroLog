<template>
  <div class="container">
    <header>
      <h1>{{ $t("sign.loginTitle") }}</h1>
    </header>
    <main>
      <img src="/logo.svg" alt="logotipo" />
      <div class="menu">
        <Button :text="$t('sign.signin')" @click="activetab = '1'" :class="activetab === '1' ? 'active' : ''" />
        <Button :text="$t('sign.signup')" @click="activetab = '2'" :class="activetab === '2' ? 'active' : ''" />
      </div>
      <form v-if="activetab === '1'" action="#" @submit.prevent="submit">
        <Input v-model="email" type="email" :label="$t('mail')" />
        <Input v-model="password" type="password" :label="$t('password')" autocomplete />
        <Button text="Login" @click="login" />
      </form>

      <form v-if="activetab === '2'" action="#" @submit.prevent="submit">
        <Input v-model="name" type="text" :label="$t('name')" />
        <Input v-model="email" type="email" :label="$t('mail')" />
        <Input v-model="password" type="password" :label="$t('password')" autocomplete />
        <Button :text="$t('sign.signup')" @click="signup" />
      </form>
      <hr />
      <Button img="google" @click="googleSignIn">
        <img src="/google.svg" alt="" />
      </Button>
      <!-- TODO: create a popup to formMessage -->
      <p>{{ formMessage }}</p>
    </main>
  </div>
</template>

<script>
  import { auth, db } from "@/firebase";
  import { getAdditionalUserInfo, GoogleAuthProvider, signInWithPopup } from "firebase/auth";
  import { doc, setDoc } from "firebase/firestore";
  import Input from "@/components/BaseInput.vue";
  import Button from "@/components/BaseButton.vue";
  import { mapGetters } from "vuex";

  export default {
    name: "Login",
    components: { Input, Button },
    data: () => ({
      activetab: "1",
      name: "",
      email: "",
      password: "",
      formMessage: "",
      signUpStatus: {},
      resetStatus: {},
    }),
    computed: {
      ...mapGetters(["getError", "getInformation"]),
    },
    methods: {
      async login() {
        await this.$store.dispatch("login", {
          email: this.email,
          password: this.password,
        });
      },
      async signup() {
        await this.$store.dispatch("signup", {
          name: this.name,
          email: this.email,
          password: this.password,
        });
        this.signUpStatus = this.getError?.signUp || this.getInformation?.signUp;
      },
      async resetPassword(data) {
        await this.$store.dispatch("resetPassword", { email: data.email });
        this.resetStatus = this.getError?.resetPassword || this.getInformation?.resetPassword;
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
                this.formMessage = this.$t("sign.tabClosed");
                break;
              default:
                this.formMessage = this.$t("sign.weirdError" + error);
                console.log(error);
                break;
            }
          });
      },
      // login() {
      //   signInWithEmailAndPassword(auth, this.email, this.password)
      //     .then(this.$router.push("/"))
      //     .catch((err) => {
      //       switch (err.code) {
      //         case "auth/invalid-email":
      //           this.formMessage = this.$t("sign.invalidMail");
      //           break;
      //         case "auth/user-not-found":
      //           this.formMessage = this.$t("sign.userNotFound");
      //           break;
      //         case "auth/wrong-password":
      //           this.formMessage = this.$t("sign.incorrectPassword");
      //           break;
      //         default:
      //           this.formMessage = this.$t("sign.incorrectEmailOrPassword");
      //           break;
      //       }
      //     });
      // },
      // signUp() {
      //   createUserWithEmailAndPassword(auth, this.newEmail, this.newPass).then(
      //     async (userCredential) => {
      //       const userId = userCredential.user.uid;
      //       console.log(userCredential);
      //       await setDoc(doc(db, "users", userId), {
      //         email: this.newEmail,
      //         name: this.createName,
      //         shelfName: this.createName,
      //       });
      //       this.$router.push("/");
      //       this.formMessage = this.$t("sign.accountCreated");
      //     },
      //     (error) => {
      //       this.formMessage = error.message;
      //       console.error(error);
      //     },
      //   );
      // },
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
