<template>
  <q-page padding>
    <header>
      <h1 class="text-h3">{{ $t("sign.loginTitle") }}</h1>
    </header>
    <q-card>
      <q-card-section>
        <img src="/logo.svg" alt="logotipo" />
      </q-card-section>
      <q-tabs v-model="tab" class="text-teal" dense>
        <q-tab name="signup" :label="$t('sign.signup')" />
        <q-tab name="signin" :label="$t('sign.signin')" />
        <q-tab name="recover" :label="$t('sign.recover')" />
      </q-tabs>
      <q-tab-panels v-model="tab" animated>
        <q-tab-panel name="signup">
          <q-form @submit="signup" @reset="onReset" class="q-gutter-md">
            <q-input v-model="name" type="text" :label="$t('sign.name')" />
            <q-input v-model="email" type="email" :label="$t('sign.mail')" />
            <q-input v-model="password" type="password" :label="$t('sign.password')" />
            <div>
              <q-btn type="submit" :label="$t('sign.signup')" color="primary" />
              <q-btn flat type="reset" :label="$t('sign.reset')" color="primary" class="q-ml-sm" />
            </div>
          </q-form>
        </q-tab-panel>
        <q-tab-panel name="signin">
          <q-form @submit="login" @reset="onReset" class="q-gutter-md">
            <q-input v-model="email" type="email" :label="$t('sign.mail')" />
            <q-input v-model="password" type="password" :label="$t('sign.password')" />
            <div>
              <q-btn type="submit" :label="$t('sign.signin')" color="primary" />
              <q-btn flat type="reset" :label="$t('sign.reset')" color="primary" class="q-ml-sm" />
            </div>
          </q-form>
        </q-tab-panel>
        <q-tab-panel name="recover">
          <q-form @submit="resetPassword" @reset="onReset" class="q-gutter-md">
            <q-input v-model="email" type="email" :label="$t('sign.mail')" />
            <div>
              <q-btn type="submit" :label="$t('sign.recover')" color="primary" />
              <q-btn flat type="reset" :label="$t('sign.reset')" color="primary" class="q-ml-sm" />
            </div>
          </q-form>
        </q-tab-panel>
      </q-tab-panels>

      <q-card-section>
        <q-separator />
      </q-card-section>

      <q-btn @click="googleSignIn" class="btn-google">
        <img src="/google.svg" alt="" />&nbsp;
        <span>{{ $t("sign.sign-google") }}</span>
      </q-btn>

      <p v-if="getError">
        <q-card-section>
          <q-separator />
        </q-card-section>
        // TODO: Incluir notificações de sucesso e erro
        {{ $t("sign." + getError.code) }}
      </p>
    </q-card>
  </q-page>
</template>

<script>
import { ref } from "vue";
import { mapGetters } from "vuex";

export default {
  name: "Login",
  setup: () => ({ tab: ref("signin") }),
  data: () => ({
    name: "",
    email: "",
    password: "",
    signUpStatus: {},
    resetStatus: {},
  }),
  computed: {
    ...mapGetters(["getError", "getInformation"]),
  },
  methods: {
    login() {
      this.$store.dispatch("login", { email: this.email, password: this.password });
    },
    signup() {
      this.$store.dispatch("signup", { name: this.name, email: this.email, password: this.password });
      this.signUpStatus = this.getError || this.getInformation?.signUp;
    },
    googleSignIn() {
      this.$store.dispatch("googleSignIn");
    },
    resetPassword() {
      this.$store.dispatch("resetPassword", { email: this.email });
      this.resetStatus = this.getError || this.getInformation?.resetPassword;
    },
    onReset() {
      this.name = "";
      this.email = "";
      this.password = "";
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
  padding: 0 1em;
  text-align: center;
}

header {
  mix-blend-mode: soft-light;
  padding: 3em 0;
}

.q-card {
  margin: 0 auto;
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
</style>
