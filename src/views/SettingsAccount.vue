<template>
  <div class="text-h6">{{ $t("settings.account-profile") }}</div>
  <q-form @submit="updateAccount" class="q-gutter-md">
    <q-input v-model="displayName" :label="$t('book.shelfname')">
      <template v-slot:prepend>
        <q-icon name="badge" />
      </template>
    </q-input>
    <q-input
      v-model="username"
      :label="$t('sign.username')"
      debounce="500"
      prefix="https://livrero.vercel.app/"
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
    <br />
    <q-btn :label="$t('settings.save')" type="submit" color="primary" icon="save" :loading="saving" />
  </q-form>
</template>

<script>
import { useI18n } from "vue-i18n";
import { mapGetters } from "vuex";

export default {
  data: () => ({
    displayName: "",
    saving: false,
    username: "",
  }),
  computed: {
    ...mapGetters(["getMyDisplayName", "getMyUsername"]),
  },
  mounted() {
    this.displayName = this.getMyDisplayName;
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
    updateAccount() {
      const payload = {
        displayName: this.displayName,
        username: this.username,
      };
      this.saving = true;
      this.$store
        .dispatch("updateAccount", payload)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.account-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.account-updated-error") }))
        .finally(() => (this.saving = false));
    },
  },
};
</script>
