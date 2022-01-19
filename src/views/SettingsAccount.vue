<template>
  <div class="text-h6">{{ $t("settings.account-profile") }}</div>
  <q-input v-model="shelfName" :label="$t('book.shelfname')">
    <template v-slot:prepend>
      <q-icon name="badge" />
    </template>
  </q-input>
  <q-select v-model="locale" :options="localeOptions" :label="$t('settings.language')" emit-value map-options>
    <template v-slot:prepend>
      <q-icon name="translate" />
    </template>
  </q-select>
  <!-- TODO: Permitir que o usuário altere seu e-mail -->
  <!-- TODO: Permitir que o usuário altere seu username -->
  <!-- TODO: Permitir que o usuário altere sua senha -->
  <!-- TODO: Permitir que o usuário adicione uma foto de perfil -->
  <br />
  <q-btn color="primary" icon="save" :loading="saving" :label="$t('settings.save')" @click="updateShelfName" />
</template>

<script>
import { useI18n } from "vue-i18n";
import { mapGetters } from "vuex";

export default {
  data: () => ({
    saving: false,
    shelfName: "",
  }),
  computed: {
    ...mapGetters(["getMyShelfName"]),
  },
  mounted() {
    this.shelfName = this.getMyShelfName;
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
    updateShelfName() {
      this.saving = true;
      this.$store
        .dispatch("updateShelfName", this.shelfName)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.shelfname-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.shelfname-updated-error") }))
        .finally(() => (this.saving = false));
    },
  },
};
</script>
