<template>
  <div class="text-h6">{{ $t("settings.account-profile") }}</div>
  <q-form @submit="updateProfile" class="q-gutter-md q-mb-md">
    <q-input v-model="displayName" :label="$t('book.shelfname')">
      <template v-slot:prepend>
        <q-icon name="badge" />
      </template>
    </q-input>
    <q-input
      v-model="username"
      :label="$t('sign.username')"
      debounce="500"
      :prefix="hostname"
      :rules="[(val) => usernameValidator(val)]"
    >
      <template v-slot:prepend>
        <q-icon name="link" />
      </template>
    </q-input>
    <q-select v-model="locale" :options="localeOptions" :label="$t('settings.language')" emit-value map-options>
      <template v-slot:prepend>
        <q-icon name="translate" />
      </template>
    </q-select>
    <!-- TODO: Permitir que o usuário altere seu e-mail -->
    <!-- TODO: Permitir que o usuário altere sua senha -->
    <!-- TODO: Permitir que o usuário adicione uma foto de perfil -->
    <q-btn :label="$t('settings.update-profile')" type="submit" color="primary" icon="save" :loading="updatindProfile" />
  </q-form>
  <q-separator />
  <q-form @submit="updateAccount" class="q-gutter-md q-mt-md">
    <q-input v-model="email" :label="$t('sign.mail')" :rules="[(val) => emailValidator(val)]">
      <template v-slot:prepend>
        <q-icon name="mail" />
      </template>
    </q-input>

    <q-btn :label="$t('settings.update-account')" type="submit" color="primary" />
  </q-form>
</template>

<script>
import { useI18n } from "vue-i18n";
import { mapGetters } from "vuex";

export default {
  data: () => ({
    email: "",
    displayName: "",
    hostname: "",
    updatingAccount: false,
    updatindProfile: false,
    username: "",
  }),
  computed: {
    ...mapGetters(["getMyEmail", "getMyDisplayName", "getMyUsername"]),
  },
  mounted() {
    this.email = this.getMyEmail;
    this.displayName = this.getMyDisplayName;
    this.hostname = window.location.hostname + "/";
    this.username = this.getMyUsername;
  },
  setup() {
    const { locale } = useI18n({ useScope: "global" });
    return {
      locale,
      localeOptions: [
        { value: "en", label: "English" },
        { value: "ja", label: "日本語" },
        { value: "pt" && "pt-BR", label: "Português" },
      ],
    };
  },
  methods: {
    usernameValidator(username) {
      if (username === this.getMyUsername) return true;
      if (["home", "add", "people", "settings", "login"].includes(username)) return false;
      if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) return false;
      return this.$store.dispatch("checkUsername", username).then((exists) => !exists);
    },
    updateProfile() {
      this.updatindProfile = true;
      this.$store
        .dispatch("updateProfile", this.displayName, this.username )
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.profile-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.profile-updated-error") }))
        .finally(() => (this.updatindProfile = false));
    },
    updateAccount() {
      this.updatingAccount = true;
      this.$store
        .dispatch("updateAccount", this.email)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.account-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.account-updated-error") }))
        .finally(() => (this.updatingAccount = false));
    },
  },
};
</script>
