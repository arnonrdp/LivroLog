<template>
  <Header />
  <!-- <h1>Definições</h1> -->
  <form action="#" @submit.prevent="submit">
      <Input v-model="shelfName" type="text" label="Nome da Estante" />
      <Button text="Salvar" @click="update" />
  </form>
  <form action="#" @submit.prevent="submit">
    <fieldset>
      <legend>Ajustes de Livros</legend>
      <Button text="Atualizar datas de leituras" @click="updateBooks" />
    </fieldset>
  </form>
  <Button text="Logout" @click="logout" />
</template>

<script>
import { getAuth, signOut } from "firebase/auth";
import Header from "@/components/TheHeader.vue";
import Input from "@/components/BaseInput.vue";
import Button from "@/components/BaseButton.vue";
import { doc, getDoc, getFirestore, updateDoc } from "@firebase/firestore";

export default {
  name: "Settings",
  data: () => ({
    shelfName: "",
  }),
  components: { Header, Input, Button },
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
      console.log("Atualizar datas de leitura")
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

legend {
  text-align: left;
}
</style>
