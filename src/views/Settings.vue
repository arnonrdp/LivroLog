<template>
  <Header />
  <!-- <h1>Definições</h1> -->
  <form action="#" @submit.prevent="submit">
    <Input v-model="shelfName" type="text" :label="$t('shelfname')">
      <Button text="Salvar" @click="update" />
    </Input>
  </form>
  <div class="locale-changer">
    <select v-model="$i18n.locale">
      <option
        v-for="locale in $i18n.availableLocales"
        :key="`locale-${locale}`"
        :value="locale"
      >
        {{ locale }}
      </option>
    </select>
  </div>
  <Button text="Logout" @click="logout" />
  <hr />
  <p>{{ $t("message.testing") }}</p>
  <Counter />
  <hr>
</template>

<script>
import { auth } from "@/firebase";
import { signOut } from "firebase/auth";

import Header from "@/components/TheHeader.vue";
import Input from "@/components/BaseInput.vue";
import Button from "@/components/BaseButton.vue";
import Counter from "@/components/counter.vue";
import Tooltip from "@adamdehaven/vue-custom-tooltip";

export default {
  name: "Settings",
  data: () => ({
    shelfName: "",
  }),
  components: { Header, Input, Button, Counter, Tooltip },
  methods: {
    async update() {
      await this.$store.commit("updateShelfName", this.shelfName);
      await this.$store.commit("gethelfName");
      this.shelfName = this.$store.state.shelfName;
    },

    logout() {
      signOut(auth).then(() => {
        this.$router.push({ name: "Login" });
      });
    },
  },
  async mounted() {
    await this.$store.getters.gethelfName;
    this.shelfName = this.$store.state.shelfName;
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
