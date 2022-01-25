<template>
  <div class="text-h6">{{ $t("settings.account") }}</div>
  <q-form @submit.prevent="updateAccount" class="q-gutter-md q-mt-md">
    <q-input v-model="email" :label="$t('sign.mail')" :rules="[(val) => emailValidator(val)]" required>
      <template v-slot:prepend>
        <q-icon name="mail" />
      </template>
    </q-input>
    <q-input v-model="password" type="password" :label="$t('sign.password')" :rules="[(val) => passValidator(val)]">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-input v-model="newPass" type="password" :label="$t('settings.pass1')" :rules="[(val) => passValidator(val)]">
      <template v-slot:prepend>
        <q-icon name="lock" />
      </template>
    </q-input>
    <q-input v-model="passConf" type="password" :label="$t('settings.pass2')" :rules="[(val) => pass2Validator(val)]">
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
    newPass: "",
    passConf: "",
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
    passValidator(password) {
      return password.length >= 6;
    },
    pass2Validator(passConf) {
      return passConf === this.newPass;
    },
    updateAccount() {
      this.updating = true;
      const credential = { email: this.email, password: this.password, newPass: this.newPass };
      this.$store
        .dispatch("updateAccount", credential)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.account-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.account-updated-error") }))
        .finally(() => (this.updating = false));
    },
  },
};
</script>
