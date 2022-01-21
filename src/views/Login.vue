<template>
  <q-page padding>
    <q-card>
      <q-card-section>
        <img src="/logo.svg" alt="logotipo" />
      </q-card-section>
      <q-tabs v-model="tab" class="text-teal">
        <q-tab name="signup" :label="$t('sign.signup')" />
        <q-tab name="signin" :label="$t('sign.signin')" />
        <q-tab name="recover" :label="$t('sign.recover')" />
      </q-tabs>
      <q-form @submit="submit(tab)" @reset="onReset" class="q-gutter-md q-ma-sm">
        <q-input dense autofocus v-if="tab === 'signup'" v-model="displayName" type="text" :label="$t('sign.name')" required />
        <q-input
          dense
          v-if="tab === 'signup'"
          v-model="username"
          type="text"
          prefix="livrero.vercel.app/"
          debounce="500"
          :label="$t('sign.username')"
          :rules="[(val) => usernameValidator(val)]"
          required
        />
        <q-input
          dense
          key="email-input"
          v-model="email"
          type="email"
          :label="$t('sign.mail')"
          :rules="[(val) => emailValidator(val)]"
          required
        />
        <q-input
          dense
          v-if="tab !== 'recover'"
          v-model="password"
          type="password"
          :label="$t('sign.password')"
          :rules="[(val) => val.length >= 6]"
          required
        />
        <q-input
          dense
          v-if="tab === 'signup'"
          v-model="passwordConfirm"
          type="password"
          :label="$t('sign.password-confirmation')"
          :rules="[(val) => passwordConfirmValidator(val)]"
          required
        />
        <div class="q-pt-md">
          <q-btn type="submit" :label="$t('sign.' + tab)" color="primary" />
          <q-btn flat type="reset" :label="$t('sign.reset')" color="primary" class="q-ml-sm" />
        </div>
      </q-form>
      <q-card-section>
        <q-separator />
      </q-card-section>
      <q-btn @click="googleSignIn" class="btn-google">
        <img src="/google.svg" alt="" />&nbsp;
        <span>{{ $t("sign.sign-google") }}</span>
      </q-btn>
    </q-card>
  </q-page>
</template>

<script>
import { ref } from "vue";

export default {
  setup: () => ({ tab: ref("signin") }),
  data: () => ({
    displayName: "",
    username: "",
    email: "",
    password: "",
    passwordConfirm: "",
  }),
  methods: {
    emailValidator(email) {
      return /^\S+@\S+\.\S+$/.test(email);
    },
    usernameValidator(username) {
      if (["home", "add", "people", "settings", "login"].includes(username)) return false;
      if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) return false;
      return this.$store.dispatch("checkUsername", username).then((exists) => !exists);
    },
    passwordConfirmValidator(passwordConfirm) {
      return passwordConfirm === this.password;
    },
    submit(tab) {
      if (tab === "signup") this.signup(this.displayName, this.username, this.email, this.password);
      if (tab === "signin") this.signin(this.email, this.password);
      if (tab === "recover") this.recover(this.email);
    },
    signup(displayName, username, email, password) {
      this.$store
        .dispatch("signup", { displayName, username, email, password })
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("sign.accountCreated") }))
        .catch((error) => this.$q.notify({ icon: "error", message: this.authErrors()[error] }));
    },
    signin(email, password) {
      this.$store
        .dispatch("login", { email, password })
        .catch((error) => this.$q.notify({ icon: "error", message: this.authErrors()[error] }));
    },
    googleSignIn() {
      this.$store
        .dispatch("googleSignIn")
        .catch((error) => this.$q.notify({ icon: "error", message: this.authErrors()[error] }));
    },
    resetPassword(email) {
      this.$store
        .dispatch("resetPassword", { email })
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("sign.password-reset") }))
        .catch((error) => this.$q.notify({ icon: "error", message: this.authErrors()[error] }));
    },
    onReset() {
      this.name = "";
      this.email = "";
      this.password = "";
      this.passwordConfirm = "";
    },
    authErrors() {
      return {
        "auth/email-already-in-use": this.$t("sign.auth-email-already-in-use"),
        "auth/incorrect-email-or-password": this.$t("sign.auth-incorrect-email-or-password"),
        "auth/internal-error": this.$t("sign.auth-internal-error"),
        "auth/invalid-email": this.$t("sign.auth-invalid-email"),
        "auth/missing-email": this.$t("sign.auth-missing-email"),
        "auth/phone-number-already-exists": this.$t("sign.auth-phone-number-already-exists"),
        "auth/popup-closed-by-user": this.$t("sign.auth-popup-closed-by-user"),
        "auth/user-not-found": this.$t("sign.auth-user-not-found"),
        "auth/weak-password": this.$t("sign.auth-weak-password"),
        "auth/wrong-password": this.$t("sign.auth-wrong-password"),
      };
    },
  },
};
</script>

<style scoped>
.q-page {
  background-image: url("../assets/bg_login.jpg");
  background-position: top center;
  background-repeat: no-repeat;
  background-size: cover;
  font-family: "SF Pro", sans-serif;
  height: 100vh;
  text-align: center;
}

.q-card {
  margin: calc((100vh - 500px) / 2) auto;
  max-width: 400px;
  padding: 2em;
}

img[alt="logotipo"] {
  width: 15em;
}

.btn-google {
  background-color: #fff;
  margin-top: 1em;
  padding: 0.5em 1em;
}

.btn-google img {
  margin-right: 0.5em;
}

.q-field {
  padding-bottom: 0;
}
</style>
