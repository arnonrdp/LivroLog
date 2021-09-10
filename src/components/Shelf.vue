<template>
  <main>
    <h1>{{ shelfName }}</h1>
    <section>
      <figure>
        <a href="#"><img src="#" alt="Livro"/></a>
        <figcaption>TÍTULO</figcaption>
      </figure>
      <figure>
        <a href="#"><img src="#" alt="Livro"/></a>
        <figcaption>TÍTULO</figcaption>
      </figure>
      <figure>
        <a href="#"><img src="#" alt="Livro"/></a>
        <figcaption>TÍTULO</figcaption>
      </figure>
    </section>
  </main>
</template>

<script>
import { getAuth } from "firebase/auth";
import { getFirestore, doc, getDoc } from "firebase/firestore";

export default {
  name: "Shelf",
  data: () => ({ shelfName: "" }),
  async mounted() {
    const auth = getAuth();
    const db = getFirestore();

    const docRef = doc(db, "users", auth.currentUser.uid);
    const docSnap = await getDoc(docRef);

    if (docSnap.exists()) {
      this.shelfName = "Estante de " + docSnap.data().name;
    } else {
      this.shelfName = "Sua Estante";
    }
  },
};
</script>

<style scoped>
main {
  margin: 0 10px;
}

h1 {
  border: 0.5px solid transparent;
  border-radius: 18px;
  color: #491f00;
  font-size: 1.5rem;
  letter-spacing: 1px;
  margin: 0;
  width: fit-content;
}

section {
  min-height: 285px;
  background-image: url("~@/assets/shelfleft.png"),
    url("~@/assets/shelfright.png"), url("~@/assets/shelfcenter.png");
  background-repeat: repeat-y, repeat-y, repeat;
  background-position: top left, top right, 240px 0;
  padding: 0 30px 15px 30px;
  border-radius: 6px;
  display: flex;
  flex-flow: row wrap;
  justify-content: space-around;
}

section figure {
  position: relative;
  display: flex;
  align-items: flex-end;
  margin: 0 30px;
  height: 143.5px;
  max-width: 80px;
}
</style>
