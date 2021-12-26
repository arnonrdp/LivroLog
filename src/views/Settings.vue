<template>
  <Header />
  <!-- <h1>Definições</h1> -->
  <form action="#" @submit.prevent="submit">
    <Input v-model="shelfName" type="text" @keyup.enter="updateShelfName" :label="$t('shelfName')" />
  </form>
  <div class="locale-changer">
    <select v-model="$i18n.locale">
      <option v-for="(locale, key) in locales" :key="key" :value="key">
        {{ locale }}
      </option>
    </select>
  </div>
  <Button :text="$t('sign.logout')" @click="logout" />
</template>

<script>
import Button from "@/components/BaseButton.vue";
import Input from "@/components/BaseInput.vue";
import Header from "@/components/TheHeader.vue";
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { mapGetters } from "vuex";

export default {
  name: "Settings",
  data: () => ({
    shelfName: "",
  }),
  components: { Header, Input, Button, Tooltip },
  computed: {
    ...mapGetters(["getUserProfile"]),
    locales() {
      return {
        en: "English",
        ja: "日本語",
        pt: "Português",
      };
    },
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
form {
  margin: auto;
  width: 70%;
}

form button {
  margin: 0;
  position: absolute;
  right: 9%;
  top: -1px;
}

input:focus ~ button {
  right: 6%;
}
</style>
