<template>
  <div class="text-h6">{{ $t("settings.profile") }}</div>
  <q-form @submit.prevent="updateProfile" class="q-gutter-md q-mb-md">
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
    <q-btn :label="$t('settings.update-profile')" type="submit" color="primary" icon="save" :loading="updating" />
  </q-form>
</template>

<script>
import { useI18n } from "vue-i18n";
import { mapGetters } from "vuex";

export default {
  data: () => ({
    displayName: "",
    hostname: "",
    updating: false,
    username: "",
  }),
  computed: {
    ...mapGetters(["getMyDisplayName", "getMyUsername"]),
  },
  mounted() {
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
      this.updating = true;
      this.$store
        .dispatch("updateProfile", { displayName: this.displayName, username: this.username })
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.profile-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.profile-updated-error") }))
        .finally(() => (this.updating = false));
    },
  },
};
</script>
