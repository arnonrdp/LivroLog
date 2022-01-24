<template>
  <div class="text-h6">{{ $t("settings.account") }}</div>
  <!-- TODO: Permitir que o usuário altere sua senha -->
  <!-- TODO: Permitir que o usuário adicione uma foto de perfil -->
  <q-form @submit.prevent="updateAccount" class="q-gutter-md q-mt-md">
    <q-input v-model="email" :label="$t('sign.mail')" :rules="[(val) => emailValidator(val)]" required>
      <template v-slot:prepend>
        <q-icon name="mail" />
      </template>
    </q-input>
    <q-input v-model="password" type="password" :label="$t('sign.password')" :rules="[(val) => passwordValidator(val)]">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-btn :label="$t('settings.update-account')" type="submit" color="primary" icon="save" :loading="updating" />
  </q-form>
</template>

<script>
import { mapGetters } from "vuex";

export default {
  data: () => ({
    email: "",
    password: "",
    updating: false,
  }),
  computed: {
    ...mapGetters(["getMyEmail"]),
  },
  mounted() {
    this.email = this.getMyEmail;
  },
  methods: {
    emailValidator(email) {
      if (email === this.email) return true;
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return false;
      return this.$store.dispatch("checkEmail", email).then((exists) => !exists);
    },
    passwordValidator(password) {
      return password.length >= 6;
    },
    updateAccount() {
      this.updating = true;
      this.$store
        .dispatch("updateAccount", this.email, this.password)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.account-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.account-updated-error") }))
        .finally(() => (this.updating = false));
    },
  },
};
</script>
