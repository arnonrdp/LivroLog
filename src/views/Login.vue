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
        <Input v-model="email" type="email" :label="$t('sign.mail')" />
        <Input v-model="password" type="password" :label="$t('sign.password')" autocomplete />
        <Button text="Login" @click="login" />
      </form>

      <form v-if="activetab === '2'" action="#" @submit.prevent="submit">
        <Input v-model="name" type="text" :label="$t('sign.name')" />
        <Input v-model="email" type="email" :label="$t('sign.mail')" />
        <Input v-model="password" type="password" :label="$t('sign.password')" autocomplete />
        <Button :text="$t('sign.signup')" @click="signup" />
      </form>
      <hr />
      <Button img="google" @click="googleSignIn">
        <img src="/google.svg" alt="" />
      </Button>
      <p v-if="getError">{{ $t("sign." + getError.code) }}</p>
    </main>
  </div>
</template>

<script>
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
      await this.$store.dispatch("login", { email: this.email, password: this.password });
    },
    async signup() {
      await this.$store.dispatch("signup", { name: this.name, email: this.email, password: this.password });
      this.signUpStatus = this.getError || this.getInformation?.signUp;
    },
    async resetPassword() {
      await this.$store.dispatch("resetPassword", { email: this.email });
      this.resetStatus = this.getError || this.getInformation?.resetPassword;
    },
    async googleSignIn() {
      await this.$store.dispatch("googleSignIn");
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
