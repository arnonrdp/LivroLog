<template>
  <Header />
  <q-card>
    <q-tabs v-model="tab" dense class="text-grey" active-color="primary" indicator-color="primary" align="justify">
      <q-tab name="account" icon="account_circle" label="Account" default />
      <q-tab name="books" icon="menu_book" label="Books" />
    </q-tabs>
    <q-separator />
    <q-tab-panels v-model="tab" animated default>
      <q-tab-panel name="account" default>
        <div class="text-h6">Account and Profile</div>
          <q-input v-model="shelfName" type="text" :label="$t('book.shelfname')" @keyup.enter="updateShelfName" />
        <q-select v-model="locale" :options="localeOptions" label="Language" emit-value map-options options-dense>
          <template>
            <q-icon name="translate" />
          </template>
        </q-select>
        <br />
        <q-btn color="primary" icon="logout" :label="$t('sign.logout')" @click="logout" />
      </q-tab-panel>
      <q-tab-panel name="books">
        <div class="text-h6">Books</div>
        <!-- TODO1: Listar livros -->
        <!-- TODO2: Adicionar datas de leitura aos livros -->
        <p>TODO: organizar os livros por data de leitura</p>
      </q-tab-panel>
    </q-tab-panels>
  </q-card>
</template>

<script>
import Button from "@/components/BaseButton.vue";
import Input from "@/components/BaseInput.vue";
import Header from "@/components/TheHeader.vue";
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { ref } from "vue";
import { mapGetters } from "vuex";
import { useI18n } from "vue-i18n";

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
  components: { Header, Input, Button, Tooltip },
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
.q-card {
  background-color: transparent;
  box-shadow: none;
}
.q-tab-panels {
  background-color: transparent;
}
</style>
