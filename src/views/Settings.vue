<template>
  <Header />
  <!-- <h1>Definições</h1> -->
  <form action="#" @submit.prevent="submit">
    <Input v-model="shelfName" type="text" :label="$t('shelfname')">
      <Button text="Salvar" @click="update" />
    </Input>
  </form>
  <Button text="Logout" @click="logout" />
  <hr />
  <p>{{ $t("message.testing") }}</p>
  <Counter />
  <hr />
  <p>{{ $t("message.hello") }}</p>
  <div class="locale-changer">
    <select v-model="$i18n.locale">
      <option
        v-for="locale in $i18n.availableLocales"
        :key="`locale-${locale}`"
        :value="locale"
        >{{ locale }}</option
      >
    </select>
  </div>
</template>

<script>
import { getAuth, signOut } from "firebase/auth";
import Header from "@/components/TheHeader.vue";
import Input from "@/components/BaseInput.vue";
import Button from "@/components/BaseButton.vue";
import { doc, getDoc, getFirestore, updateDoc } from "@firebase/firestore";
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
      const auth = getAuth();
      const db = getFirestore();
      const userID = auth.currentUser.uid;
      const userRef = doc(db, "users", userID);
      const userSnap = await getDoc(userRef);

      await updateDoc(userRef, {
        shelfName: this.shelfName,
      });
    },
    updateBooks() {
      console.log("Atualizar datas de leitura");
    },
    logout() {
      const auth = getAuth();
      signOut(auth).then(() => {
        this.$router.push({ name: "Login" });
      });
    },
  },
  async mounted() {
    const auth = getAuth();
    const db = getFirestore();
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
