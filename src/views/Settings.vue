<template>
  <q-page padding class="flex column inline">
    <q-tabs v-model="tab" inline-label active-color="primary" indicator-color="primary" align="justify">
      <q-tab name="account" icon="account_circle" :label="$t('settings.account')" default />
      <q-tab name="books" icon="menu_book" :label="$t('settings.books')" />
    </q-tabs>
    <q-separator />
    <q-tab-panels v-model="tab" animated default>
      <q-tab-panel name="account" default>
        <div class="text-h6">{{ $t("settings.account-profile") }}</div>
        <q-input v-model="shelfName" type="text" :label="$t('book.shelfname')" @keyup.enter="updateShelfName">
          <template v-slot:prepend>
            <q-icon name="badge" />
          </template>
        </q-input>
        <q-select v-model="locale" :options="localeOptions" :label="$t('settings.language')" emit-value map-options>
          <template v-slot:prepend>
            <q-icon name="translate" />
          </template>
        </q-select>
        <br />
        <q-btn flat color="primary" icon="logout" :label="$t('sign.logout')" @click="logout" />
      </q-tab-panel>
      <q-tab-panel name="books">
        <div class="text-h6">Books</div>
        <!-- TODO1: Listar livros -->
        <!-- TODO2: Adicionar datas de leitura aos livros -->
        <p>TODO: organizar os livros por data de leitura</p>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script>
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { mapGetters } from "vuex";

export default {
  name: "Settings",
  data: () => ({
    shelfName: "",
  }),
  setup() {
    const { locale } = useI18n({ useScope: "global" });
    return {
      locale,
      localeOptions: [
        { value: "en", label: "English" },
        { value: "ja", label: "日本語" },
        { value: "pt" && "pt-BR", label: "Português" },
      ],
      tab: ref("account"),
    };
  },
  components: { Tooltip },
  computed: {
    ...mapGetters(["getUserProfile"]),
  },
  mounted() {
    this.shelfName = this.getUserProfile.shelfName;
  },
  methods: {
    updateShelfName() {
      this.$store.dispatch("updateShelfName", this.shelfName);
    },
    logout() {
      this.$store.dispatch("logout");
    },
    //TODO: Função para atualizar datas de leitura
  },
};
</script>

<style scoped>
.q-page {
  width: 35rem;
}
.q-tab-panels {
  background-color: transparent;
}
</style>
