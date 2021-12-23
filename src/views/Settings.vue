<template>
  <Header />
  <!-- <h1>Definições</h1> -->
  <form action="#" @submit.prevent="submit">
    <Input v-model="shelfName" type="text" :label="$t('shelfName')">
      <Button text="Salvar" @click="update" />
    </Input>
  </form>
  <div class="locale-changer">
    <select v-model="$i18n.locale">
      <option v-for="(locale, key) in locales" :key="key" :value="key">
        {{ locale }}
      </option>
    </select>
  </div>
  <Button :text="$t('sign.logout')" @click="logout" />
  <hr />
  <p>{{ $t("message.testing") }}</p>
  <Counter />
</template>

<script>
import Button from "@/components/BaseButton.vue";
import Input from "@/components/BaseInput.vue";
import Counter from "@/components/counter.vue";
import Header from "@/components/TheHeader.vue";
import { auth, db } from "@/firebase";
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { doc, getDoc, updateDoc } from "@firebase/firestore";

export default {
  name: "Settings",
  data: () => ({
    shelfName: "",
  }),
  components: { Header, Input, Button, Counter, Tooltip },
  computed: {
    locales() {
      return {
        en: "English",
        ja: "日本語",
        pt: "Português",
      };
    },
  },
  methods: {
    async update() {
      const userID = auth.currentUser.uid;
      const userRef = doc(db, "users", userID);

      await updateDoc(userRef, {
        shelfName: this.shelfName,
      });
    },
    logout() {
      this.$store.dispatch("logout");
    },
    //TODO: Função para atualizar datas de leitura
  },
  async mounted() {
    const userID = auth.currentUser.uid;
    const userRef = doc(db, "users", userID);
    const userSnap = await getDoc(userRef);

    this.shelfName = userSnap.data().shelfName;
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
