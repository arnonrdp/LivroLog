<template>
  <main>
    <h1>{{ shelfName }}</h1>
    <section>
      <figure v-for="book in books" :key="book.id">
        <Tooltip :label="book.title" position="is-bottom">
          <a href="#"><img :src="book.thumbnail" :alt="`Livro ${book.title}`"/></a>
        </Tooltip>
      </figure>
    </section>
  </main>
</template>

<script>
import { getAuth } from "firebase/auth";
import {
  collection,
  doc,
  getDoc,
  getDocs,
  getFirestore,
  query,
} from "firebase/firestore";
import Tooltip from "@adamdehaven/vue-custom-tooltip";

export default {
  name: "Shelf",
  components: { Tooltip },
  data: () => ({ shelfName: "", books: [] }),
  async mounted() {
    const auth = getAuth();
    const db = getFirestore();
    const userID = auth.currentUser.uid;
    const userRef = doc(db, "users", userID);
    const userSnap = await getDoc(userRef);

    userSnap.exists()
      ? (this.shelfName = "Estante de " + userSnap.data().shelfName)
      : (this.shelfName = "Sua Estante");

    const userBooksRef = query(collection(db, "users", userID, "addedBooks"));
    const querySnapshot = await getDocs(userBooksRef);
    querySnapshot.forEach((doc) => {
      this.books.push({
        id: doc.id,
        addedIn: doc.data().addedIn,
        readIn: doc.data().readIn,
      });
    });

    this.books.map(async (book) => {
      const booksRef = doc(db, "books", book.id);
      const bookSnap = await getDoc(booksRef);

      book.authors = bookSnap.data().authors;
      book.title = bookSnap.data().title;
      book.thumbnail = bookSnap.data().thumbnail;
    });
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
  background-image: url("~@/assets/shelfleft.png"),
    url("~@/assets/shelfright.png"), url("~@/assets/shelfcenter.png");
  background-repeat: repeat-y, repeat-y, repeat;
  background-position: top left, top right, 240px 0;
  border-radius: 6px;
  display: flex;
  flex-flow: row wrap;
  justify-content: space-around;
  min-height: 285px;
  padding: 0 30px 15px 30px;
}

section figure {
  align-items: flex-end;
  display: flex;
  height: 143.5px;
  margin: 0 30px;
  max-width: 80px;
  position: relative;
}

img {
  height: 115px;
}
</style>
